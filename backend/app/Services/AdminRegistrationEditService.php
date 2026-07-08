<?php
// [IN]: Admin-edited registration groups, member pigeons, and progressive stages / 后台编辑的报名组、会员足环与递进阶段
// [OUT]: Rebuilt registration snapshots, confirmed progressive rows, cascade cleanup, and audit logs / 重建后的报名快照、已确认递进行、联动清理与审计日志
// [POS]: Backend admin registration data editing service / 后端后台报名数据编辑服务
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Models\AdminLog;
use App\Models\Member;
use App\Models\Pigeon;
use App\Models\ProgressiveStageEntry;
use App\Models\Race;
use App\Models\RaceProject;
use App\Models\Registration;
use App\Models\RegistrationCategory;
use App\Models\RegistrationEntry;
use App\Models\RegistrationEntryPigeon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminRegistrationEditService
{
    public function __construct(private readonly RaceCacheService $cache) {}

    public function registrationFormData(Registration $registration): array
    {
        $registration->loadMissing(['race.projects', 'member.pigeons', 'entries.pigeons', 'progressiveStageEntries']);

        $race = $registration->race;
        $member = $registration->member;

        return [
            'race' => $race,
            'member' => $member,
            'pigeons' => $this->memberPigeons($member)->values(),
            'standard_projects' => $this->standardProjects($race),
            'progressive_categories' => $this->progressiveCategories($race),
            'standard_groups' => $this->standardGroupsFromRegistration($registration),
            'progressive_groups' => $this->progressiveGroupsForMember($race, $member),
        ];
    }

    public function categoryMemberFormData(RegistrationCategory $category, ?Member $member): array
    {
        $category->loadMissing(['race', 'stageProjects']);

        return [
            'category' => $category,
            'member' => $member,
            'pigeons' => $member ? $this->memberPigeons($member)->values() : collect(),
            'progressive_categories' => collect([$category]),
            'progressive_groups' => $member ? $this->progressiveGroupsForMember($category->race, $member, $category) : [],
        ];
    }

    public function updateRegistration(Registration $registration, array $payload, ?int $adminId, ?string $ipAddress = null): array
    {
        return DB::transaction(function () use ($registration, $payload, $adminId, $ipAddress): array {
            $registration = Registration::query()
                ->with(['race', 'member'])
                ->whereKey($registration->id)
                ->lockForUpdate()
                ->firstOrFail();

            $race = $registration->race;
            $member = $registration->member;
            $before = $this->auditSummary($registration);

            $standard = $this->normalizeStandardPayload($race, $member, $payload['standard_groups'] ?? []);
            $progressiveResult = $this->writeProgressivePayload($race, $member, $payload['progressive_groups'] ?? [], $registration, $adminId);

            $registration->entries()->delete();
            $this->writeStandardSnapshots($registration, $standard['entries']);

            $registration->forceFill([
                'total_amount_cent' => $standard['total_amount_cent'] + $this->progressiveAmountForRegistration($registration),
                'status' => RegistrationStatus::Confirmed,
                'confirmed_at' => now(),
                'confirmed_by' => $adminId,
                'submitted_at' => $registration->submitted_at ?? now(),
            ])->save();

            $this->cache->forgetBootstrap($race, $member);
            $registration->refresh()->load(['entries.pigeons', 'progressiveStageEntries']);
            $this->log('admin_registration_data_updated', $registration, $adminId, $ipAddress, [
                'before' => $before,
                'after' => $this->auditSummary($registration),
                'removed_progressive_groups' => $progressiveResult['removed_groups'],
            ]);

            return $progressiveResult;
        });
    }

    public function updateCategoryMember(RegistrationCategory $category, Member $member, array $payload, ?int $adminId, ?string $ipAddress = null): array
    {
        return DB::transaction(function () use ($category, $member, $payload, $adminId, $ipAddress): array {
            $category = RegistrationCategory::query()
                ->with(['race', 'stageProjects'])
                ->whereKey($category->id)
                ->lockForUpdate()
                ->firstOrFail();
            $member = Member::query()->whereKey($member->id)->lockForUpdate()->firstOrFail();

            $result = $this->writeProgressivePayload($category->race, $member, [
                $category->id => $payload['stage_groups'] ?? [],
            ], null, $adminId);

            $this->cache->forgetBootstrap($category->race, $member);
            $this->log('admin_progressive_stage_data_updated', $category, $adminId, $ipAddress, [
                'member_id' => $member->id,
                'loft_number' => $member->loft_number,
                'removed_progressive_groups' => $result['removed_groups'],
            ]);

            return $result;
        });
    }

    private function normalizeStandardPayload(Race $race, Member $member, array $projectGroups): array
    {
        $projects = $this->standardProjects($race)->keyBy('id');
        $pigeons = $this->memberPigeons($member)->keyBy('id');
        $entries = [];
        $totalAmount = 0;

        foreach ($projectGroups as $projectId => $groups) {
            $project = $projects->get((int) $projectId);
            if (! $project instanceof RaceProject) {
                throw new RegistrationRuleException('project_disabled', '报名项目不存在或已停用。');
            }

            $validatedGroups = $this->validateGroups($project, $groups, $pigeons, enforceNoRepeatFlag: true);
            foreach ($validatedGroups as $index => $groupPigeons) {
                $entries[] = [
                    'project' => $project,
                    'pigeons' => $groupPigeons,
                    'group_index' => $index + 1,
                ];
                $totalAmount += (int) $project->price_cent;
            }
        }

        return ['entries' => $entries, 'total_amount_cent' => $totalAmount];
    }

    private function writeProgressivePayload(Race $race, Member $member, array $categoryGroups, ?Registration $registration, ?int $adminId): array
    {
        $registrationAmount = 0;
        $removedGroups = [];

        foreach ($categoryGroups as $categoryId => $stageGroups) {
            $category = RegistrationCategory::query()
                ->with(['stageProjects'])
                ->where('race_id', $race->id)
                ->where('type', RegistrationCategory::TYPE_PROGRESSIVE)
                ->whereKey((int) $categoryId)
                ->first();

            if (! $category instanceof RegistrationCategory) {
                throw new RegistrationRuleException('progressive_category_disabled', '递进报名类别不存在。');
            }

            $stageProjects = $category->stageProjects
                ->where('is_enabled', true)
                ->keyBy('id');
            $editedMaps = [];

            foreach ($stageGroups as $projectId => $groups) {
                $project = $stageProjects->get((int) $projectId);
                if (! $project instanceof RaceProject) {
                    throw new RegistrationRuleException('progressive_stage_not_configured', "类别 {$category->name} 的阶段项目不存在或已停用。");
                }

                $result = $this->replaceProgressiveStage($race, $member, $category, $project, $groups, $registration, $adminId);
                $editedMaps[(int) $project->stage_order] = $result['group_key_map'];
                if ($registration && (int) $project->stage_order > 1) {
                    $registrationAmount += $result['amount_cent'];
                }
            }

            $cascade = $this->cascadeProgressiveStages($member, $category, $editedMaps, $adminId);
            $removedGroups = [...$removedGroups, ...$cascade['removed_groups']];
        }

        return [
            'registration_amount_cent' => $registrationAmount,
            'removed_groups' => $removedGroups,
        ];
    }

    private function replaceProgressiveStage(Race $race, Member $member, RegistrationCategory $category, RaceProject $project, array $groups, ?Registration $registration, ?int $adminId): array
    {
        $pigeons = $this->memberPigeons($member)->keyBy('id');
        $validatedGroups = $this->validateGroups($project, $groups, $pigeons, enforceNoRepeatFlag: false);
        $oldGroups = $this->progressiveStageGroups($member, $category, $project);

        ProgressiveStageEntry::query()
            ->where('member_id', $member->id)
            ->where('registration_category_id', $category->id)
            ->where('race_project_id', $project->id)
            ->delete();

        $source = (int) $project->stage_order <= 1
            ? ProgressiveStageEntry::SOURCE_IMPORT
            : ProgressiveStageEntry::SOURCE_MEMBER;
        $registrationId = $registration && (int) $project->stage_order > 1 ? $registration->id : null;
        $newGroups = [];

        foreach ($validatedGroups as $index => $groupPigeons) {
            $groupKey = $this->groupSignature($groupPigeons->pluck('id')->all());
            $groupIndex = $index + 1;
            $newGroups[$groupIndex] = [
                'group_key' => $groupKey,
                'pigeon_ids' => $groupPigeons->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
                'ring_numbers' => $groupPigeons->pluck('ring_number')->values()->all(),
            ];

            $this->createProgressiveRows($race, $member, $category, $project, $groupPigeons, $groupKey, $groupIndex, $registrationId, $source, $adminId);
        }

        return [
            'amount_cent' => count($validatedGroups) * (int) $project->price_cent,
            'group_key_map' => $this->groupKeyMapByIndex($oldGroups, $newGroups),
        ];
    }

    private function cascadeProgressiveStages(Member $member, RegistrationCategory $category, array $editedMaps, ?int $adminId): array
    {
        $removedGroups = [];
        $previousMap = [];
        $stages = $category->stageProjects;

        foreach ($stages as $stage) {
            $stageOrder = (int) $stage->stage_order;
            $ownMap = $editedMaps[$stageOrder] ?? [];

            if ($stageOrder <= 1) {
                $previousMap = $ownMap;
                continue;
            }

            $previousStage = $stages->first(fn (RaceProject $candidate): bool => (int) $candidate->stage_order === $stageOrder - 1);
            if (! $previousStage instanceof RaceProject) {
                continue;
            }

            $eligible = $this->progressiveStageGroups($member, $category, $previousStage)
                ->mapWithKeys(fn (array $group): array => [$group['group_key'] => $group])
                ->all();

            $currentGroups = $this->progressiveStageGroups($member, $category, $stage);
            $nextMap = [];
            foreach ($currentGroups as $group) {
                $target = $previousMap[$group['group_key']] ?? null;
                if ($target !== null && isset($eligible[$target['group_key']])) {
                    $this->replaceExistingProgressiveGroup($member, $category, $stage, $group, $eligible[$target['group_key']], $adminId);
                    $nextMap[$group['group_key']] = $eligible[$target['group_key']];
                    continue;
                }

                if (isset($eligible[$group['group_key']])) {
                    continue;
                }

                $this->deleteProgressiveGroup($member, $category, $stage, $group['group_key']);
                $removedGroups[] = [
                    'category' => $category->name,
                    'stage' => $stage->name,
                    'rings' => $group['ring_numbers'],
                ];
            }

            $finalKeys = $this->progressiveStageGroups($member, $category, $stage)
                ->pluck('group_key')
                ->flip();
            $ownMap = collect($ownMap)
                ->filter(fn (array $target): bool => $finalKeys->has($target['group_key']))
                ->all();
            $previousMap = $nextMap + $ownMap;
        }

        return ['removed_groups' => $removedGroups];
    }

    private function replaceExistingProgressiveGroup(Member $member, RegistrationCategory $category, RaceProject $stage, array $oldGroup, array $newGroup, ?int $adminId): void
    {
        $first = ProgressiveStageEntry::query()
            ->where('member_id', $member->id)
            ->where('registration_category_id', $category->id)
            ->where('race_project_id', $stage->id)
            ->where('group_key', $oldGroup['group_key'])
            ->first();

        if (! $first instanceof ProgressiveStageEntry) {
            return;
        }

        $order = array_flip(array_map('intval', $newGroup['pigeon_ids']));
        $pigeons = Pigeon::query()
            ->whereIn('id', $newGroup['pigeon_ids'])
            ->get()
            ->sortBy(fn (Pigeon $pigeon): int => $order[(int) $pigeon->id] ?? PHP_INT_MAX)
            ->values();

        $this->deleteProgressiveGroup($member, $category, $stage, $oldGroup['group_key']);
        $this->createProgressiveRows(
            $stage->race,
            $member,
            $category,
            $stage,
            $pigeons,
            $newGroup['group_key'],
            (int) $first->group_index,
            $first->registration_id,
            $first->source,
            $adminId,
        );
    }

    private function deleteProgressiveGroup(Member $member, RegistrationCategory $category, RaceProject $stage, string $groupKey): void
    {
        ProgressiveStageEntry::query()
            ->where('member_id', $member->id)
            ->where('registration_category_id', $category->id)
            ->where('race_project_id', $stage->id)
            ->where('group_key', $groupKey)
            ->delete();
    }

    private function createProgressiveRows(Race $race, Member $member, RegistrationCategory $category, RaceProject $project, Collection $pigeons, string $groupKey, int $groupIndex, ?int $registrationId, string $source, ?int $adminId): void
    {
        foreach ($pigeons->values() as $order => $pigeon) {
            ProgressiveStageEntry::query()->create([
                'registration_id' => $registrationId,
                'race_id' => $race->id,
                'registration_category_id' => $category->id,
                'race_project_id' => $project->id,
                'member_id' => $member->id,
                'group_key' => $groupKey,
                'group_index' => $groupIndex,
                'group_size_snapshot' => $project->group_size,
                'pigeon_id' => $pigeon->id,
                'pigeon_sort_order' => $order + 1,
                'loft_number_snapshot' => $member->loft_number,
                'participant_name_snapshot' => $member->participant_name,
                'ring_number_snapshot' => $pigeon->ring_number,
                'project_name_snapshot' => $project->name,
                'price_cent_snapshot' => $project->price_cent,
                'status' => RegistrationStatus::Confirmed,
                'source' => $source,
                'submitted_at' => now(),
                'confirmed_at' => now(),
                'confirmed_by' => $adminId,
            ]);
        }
    }

    private function writeStandardSnapshots(Registration $registration, array $entries): void
    {
        foreach ($entries as $entry) {
            $project = $entry['project'];
            $snapshot = RegistrationEntry::query()->create([
                'registration_id' => $registration->id,
                'race_project_id' => $project->id,
                'project_name_snapshot' => $project->name,
                'group_size_snapshot' => $project->group_size,
                'price_cent_snapshot' => $project->price_cent,
                'group_index' => $entry['group_index'],
                'created_at' => now(),
            ]);

            RegistrationEntryPigeon::query()->insert($entry['pigeons']->values()->map(fn (Pigeon $pigeon, int $order): array => [
                'registration_entry_id' => $snapshot->id,
                'pigeon_id' => $pigeon->id,
                'ring_number_snapshot' => $pigeon->ring_number,
                'sort_order' => $order + 1,
                'created_at' => now(),
            ])->all());
        }
    }

    private function validateGroups(RaceProject $project, array $groups, Collection $pigeons, bool $enforceNoRepeatFlag): array
    {
        $validated = [];
        $seen = [];
        $usage = [];

        foreach ($groups as $group) {
            $pigeonIds = collect($group['pigeon_ids'] ?? [])
                ->map(fn ($id): int => (int) $id)
                ->filter()
                ->unique()
                ->values();

            if ($pigeonIds->isEmpty()) {
                continue;
            }

            if ($pigeonIds->count() !== (int) $project->group_size) {
                throw new RegistrationRuleException('group_size_mismatch', "项目 {$project->name} 必须选择 {$project->group_size} 羽。");
            }

            $signature = $this->groupSignature($pigeonIds->all());
            if (isset($seen[$signature])) {
                throw new RegistrationRuleException('duplicate_group', "项目 {$project->name} 已存在相同足环组合。");
            }
            $seen[$signature] = true;

            $groupPigeons = $pigeonIds->map(function (int $pigeonId) use ($pigeons): Pigeon {
                $pigeon = $pigeons->get($pigeonId);
                if (! $pigeon instanceof Pigeon) {
                    throw new RegistrationRuleException('pigeon_not_owned', '存在不属于当前会员或不可报名的足环。', 403);
                }

                return $pigeon;
            });

            foreach ($pigeonIds as $pigeonId) {
                $usage[$pigeonId] = ($usage[$pigeonId] ?? 0) + 1;
            }

            $validated[] = $groupPigeons;
        }

        if ($project->max_entries_per_member !== null && count($validated) > (int) $project->max_entries_per_member) {
            throw new RegistrationRuleException('project_entry_limit_exceeded', "项目 {$project->name} 已超过每会员报名上限。");
        }

        foreach ($usage as $count) {
            if ($enforceNoRepeatFlag && ! $project->allow_repeat_pigeon_in_project && $count > 1) {
                throw new RegistrationRuleException('repeat_pigeon_not_allowed', "项目 {$project->name} 不允许同一足环重复进入多个组合。");
            }
            if ($project->max_usage_per_pigeon !== null && $count > (int) $project->max_usage_per_pigeon) {
                throw new RegistrationRuleException('pigeon_usage_limit_exceeded', "项目 {$project->name} 已超过每只足环最大使用次数。");
            }
        }

        return $validated;
    }

    private function standardGroupsFromRegistration(Registration $registration): array
    {
        return $registration->entries
            ->groupBy('race_project_id')
            ->map(fn (Collection $entries): array => $entries
                ->sortBy('group_index')
                ->map(fn (RegistrationEntry $entry): array => [
                    'pigeon_ids' => $entry->pigeons->sortBy('sort_order')->pluck('pigeon_id')->map(fn ($id): int => (int) $id)->values()->all(),
                ])
                ->values()
                ->all())
            ->all();
    }

    private function progressiveGroupsForMember(Race $race, Member $member, ?RegistrationCategory $onlyCategory = null): array
    {
        return $this->progressiveCategories($race, $onlyCategory)
            ->mapWithKeys(fn (RegistrationCategory $category): array => [
                $category->id => $category->stageProjects
                    ->mapWithKeys(fn (RaceProject $project): array => [
                        $project->id => $this->progressiveStageGroups($member, $category, $project)
                            ->map(fn (array $group): array => ['pigeon_ids' => $group['pigeon_ids']])
                            ->values()
                            ->all(),
                    ])
                    ->all(),
            ])
            ->all();
    }

    private function progressiveStageGroups(Member $member, RegistrationCategory $category, RaceProject $project): Collection
    {
        return ProgressiveStageEntry::query()
            ->where('member_id', $member->id)
            ->where('registration_category_id', $category->id)
            ->where('race_project_id', $project->id)
            ->orderBy('group_index')
            ->orderBy('pigeon_sort_order')
            ->get()
            ->groupBy(fn (ProgressiveStageEntry $entry): string => $entry->group_key ?: (string) $entry->pigeon_id)
            ->map(function (Collection $group): array {
                $first = $group->sortBy('pigeon_sort_order')->first();

                return [
                    'group_key' => $first->group_key ?: (string) $first->pigeon_id,
                    'group_index' => (int) $first->group_index,
                    'pigeon_ids' => $group->sortBy('pigeon_sort_order')->pluck('pigeon_id')->map(fn ($id): int => (int) $id)->values()->all(),
                    'ring_numbers' => $group->sortBy('pigeon_sort_order')->pluck('ring_number_snapshot')->values()->all(),
                ];
            })
            ->sortBy('group_index')
            ->values();
    }

    private function groupKeyMapByIndex(iterable $oldGroups, array $newGroups): array
    {
        $map = [];
        foreach ($oldGroups as $oldGroup) {
            $index = (int) ($oldGroup['group_index'] ?? 0);
            $newGroup = $newGroups[$index] ?? null;
            if ($newGroup !== null && $oldGroup['group_key'] !== $newGroup['group_key']) {
                $map[$oldGroup['group_key']] = $newGroup;
            }
        }

        return $map;
    }

    private function memberPigeons(Member $member): Collection
    {
        return $member->pigeons()
            ->where('status', 'normal')
            ->orderBy('ring_number')
            ->get();
    }

    private function standardProjects(Race $race): Collection
    {
        return $race->projects()
            ->where('project_type', RaceProject::TYPE_STANDARD)
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function progressiveCategories(Race $race, ?RegistrationCategory $onlyCategory = null): Collection
    {
        return RegistrationCategory::query()
            ->with(['stageProjects'])
            ->where('race_id', $race->id)
            ->where('type', RegistrationCategory::TYPE_PROGRESSIVE)
            ->when($onlyCategory, fn ($query) => $query->whereKey($onlyCategory->id))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function groupSignature(array $pigeonIds): string
    {
        $ids = array_map('intval', $pigeonIds);
        sort($ids, SORT_NUMERIC);

        return implode(':', $ids);
    }

    private function auditSummary(Registration $registration): array
    {
        $registration->loadMissing(['entries.pigeons', 'progressiveStageEntries']);

        return [
            'total_amount_cent' => (int) $registration->total_amount_cent,
            'standard_entries' => $registration->entries->count(),
            'progressive_groups' => $registration->progressiveStageEntries
                ->groupBy(fn (ProgressiveStageEntry $entry): string => $entry->race_project_id.':'.($entry->group_key ?: $entry->pigeon_id))
                ->count(),
        ];
    }

    private function progressiveAmountForRegistration(Registration $registration): int
    {
        return ProgressiveStageEntry::query()
            ->where('registration_id', $registration->id)
            ->get()
            ->groupBy(fn (ProgressiveStageEntry $entry): string => $entry->race_project_id.':'.($entry->group_key ?: $entry->pigeon_id))
            ->sum(fn (Collection $group): int => (int) $group->first()->price_cent_snapshot);
    }

    private function log(string $action, object $target, ?int $adminId, ?string $ipAddress, array $detail): void
    {
        AdminLog::query()->create([
            'admin_id' => $adminId,
            'action' => $action,
            'target_type' => $target::class,
            'target_id' => $target->id,
            'detail' => $detail,
            'ip_address' => $ipAddress,
            'created_at' => now(),
        ]);
    }
}
