<?php

// [IN]: Member, race, config version, idempotency key, and normalized entries / 会员、赛事、配置版本、幂等键与标准化报名项目
// [OUT]: Validated registration with readable number, unique groups, and bootstrap cache invalidation / 带可读编号、唯一组合与初始化缓存失效的报名快照
// [POS]: Backend trusted registration transaction service / 后端可信报名事务服务
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Services;

use App\Enums\RegistrationStatus;
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

class RegistrationSubmissionService
{
    public function __construct(private readonly RaceCacheService $cache) {}

    public function submit(Member $member, Race $race, int $configVersion, string $idempotencyKey, array $entries, array $progressiveEntries = []): Registration
    {
        $existingSameRequest = Registration::query()
            ->where('member_id', $member->id)
            ->where('race_id', $race->id)
            ->where('idempotency_key', $idempotencyKey)
            ->with(['entries.pigeons', 'progressiveStageEntries'])
            ->first();

        if ($existingSameRequest) {
            $this->cache->forgetBootstrap($race, $member);

            return $existingSameRequest;
        }

        $this->assertRaceCanAccept($race, $configVersion);

        $projects = $race->projects()
            ->where('is_enabled', true)
            ->where('project_type', RaceProject::TYPE_STANDARD)
            ->get()
            ->keyBy('id');
        $pigeons = $member->pigeons()
            ->where('status', 'normal')
            ->whereIn('id', $this->flattenPigeonIds($entries))
            ->get()
            ->keyBy('id');
        $standardValidated = $this->validateEntries($entries, $projects, $pigeons, true);
        $progressiveValidated = $this->validateProgressiveEntries($member, $race, $progressiveEntries);

        if ($standardValidated['entries'] === [] && $progressiveValidated['entries'] === []) {
            throw new RegistrationRuleException('empty_entries', '请至少选择一项报名项目。');
        }

        return DB::transaction(function () use ($member, $race, $idempotencyKey, $standardValidated, $progressiveValidated): Registration {
            $registration = Registration::query()
                ->where('race_id', $race->id)
                ->where('member_id', $member->id)
                ->lockForUpdate()
                ->first();

            if ($registration && ! $race->allow_member_edit) {
                throw new RegistrationRuleException('registration_already_submitted', '该赛事已提交报名，当前赛事不允许会员自行修改。', 409);
            }

            if ($registration && $race->registration_end_at < now()) {
                throw new RegistrationRuleException('registration_closed', '报名已截止，不能修改报名。', 409);
            }

            $registration ??= new Registration([
                'race_id' => $race->id,
                'member_id' => $member->id,
                'registration_no' => $this->makeRegistrationNo($race, $member),
            ]);

            $registration->fill([
                'total_amount_cent' => $standardValidated['total_amount_cent'] + $progressiveValidated['total_amount_cent'],
                'status' => $race->require_admin_confirm ? RegistrationStatus::PendingConfirmation : RegistrationStatus::Submitted,
                'idempotency_key' => $idempotencyKey,
                'submitted_at' => now(),
            ])->save();

            $registration->entries()->delete();
            $this->writeSnapshots($registration, $standardValidated['entries']);
            $this->writeProgressiveSnapshots($registration, $progressiveValidated['entries'], $race);
            $this->cache->forgetBootstrap($race, $member);

            return $registration->fresh(['entries.pigeons', 'progressiveStageEntries']);
        });
    }

    public function validateEntries(array $entries, Collection $projects, Collection $pigeons, bool $allowEmpty = false): array
    {
        if ($entries === []) {
            if ($allowEmpty) {
                return ['entries' => [], 'total_amount_cent' => 0];
            }

            throw new RegistrationRuleException('empty_entries', '请至少选择一项报名项目。');
        }

        $normalized = [];
        $entryCountByProject = [];
        $pigeonUsageByProject = [];
        $groupSignaturesByProject = [];
        $totalAmount = 0;

        foreach ($entries as $index => $entry) {
            $project = $projects->get((int) ($entry['project_id'] ?? 0));
            if (! $project instanceof RaceProject) {
                throw new RegistrationRuleException('project_disabled', '报名项目不存在或已停用。');
            }

            $pigeonIds = array_values(array_unique(array_map('intval', $entry['pigeon_ids'] ?? [])));
            if (count($pigeonIds) !== $project->group_size) {
                throw new RegistrationRuleException('group_size_mismatch', "项目 {$project->name} 必须选择 {$project->group_size} 羽。");
            }

            $groupSignature = $this->groupSignature($pigeonIds);
            if (isset($groupSignaturesByProject[$project->id][$groupSignature])) {
                throw new RegistrationRuleException('duplicate_group', "项目 {$project->name} 已存在相同足环组合。");
            }
            $groupSignaturesByProject[$project->id][$groupSignature] = true;

            $entryPigeons = [];
            foreach ($pigeonIds as $pigeonId) {
                $pigeon = $pigeons->get($pigeonId);
                if (! $pigeon instanceof Pigeon) {
                    throw new RegistrationRuleException('pigeon_not_owned', '存在不属于当前会员或不可报名的足环。', 403);
                }
                if ((int) $pigeon->pigeon_library_id !== (int) $project->pigeon_library_id) {
                    throw new RegistrationRuleException('pigeon_not_owned', '存在不属于当前项目足环库的足环。', 403);
                }
                $entryPigeons[] = $pigeon;
                $pigeonUsageByProject[$project->id][$pigeonId] = ($pigeonUsageByProject[$project->id][$pigeonId] ?? 0) + 1;
            }

            $entryCountByProject[$project->id] = ($entryCountByProject[$project->id] ?? 0) + 1;
            $this->assertProjectLimits($project, $entryCountByProject[$project->id], $pigeonUsageByProject[$project->id]);

            $totalAmount += $project->price_cent;
            $normalized[] = [
                'project' => $project,
                'pigeons' => $entryPigeons,
                'group_index' => $entryCountByProject[$project->id],
                'source_index' => $index,
            ];
        }

        return ['entries' => $normalized, 'total_amount_cent' => $totalAmount];
    }

    public function validateProgressiveEntries(Member $member, Race $race, array $entries): array
    {
        if ($entries === []) {
            return ['entries' => [], 'total_amount_cent' => 0];
        }

        $categories = RegistrationCategory::query()
            ->with(['currentStage', 'stageProjects'])
            ->where('race_id', $race->id)
            ->where('type', RegistrationCategory::TYPE_PROGRESSIVE)
            ->where('is_enabled', true)
            ->whereIn('id', collect($entries)->pluck('category_id')->map(fn ($id): int => (int) $id)->filter()->unique())
            ->get()
            ->keyBy('id');

        $pigeons = $member->pigeons()
            ->where('status', 'normal')
            ->whereIn('id', $this->flattenProgressivePigeonIds($entries))
            ->get()
            ->keyBy('id');

        $normalized = [];
        $totalAmount = 0;

        foreach ($entries as $entry) {
            $category = $categories->get((int) ($entry['category_id'] ?? 0));
            if (! $category instanceof RegistrationCategory) {
                throw new RegistrationRuleException('progressive_category_disabled', '递进报名类别不存在或已停用。');
            }

            $project = $category->currentStage;
            if (! $project instanceof RaceProject) {
                throw new RegistrationRuleException('progressive_stage_not_configured', "类别 {$category->name} 尚未配置当前开放阶段。");
            }

            if ((int) ($entry['stage_project_id'] ?? 0) !== $project->id) {
                throw new RegistrationRuleException('progressive_stage_not_current', "类别 {$category->name} 当前只能报名 {$project->name}。");
            }

            if (! $project->is_enabled || $project->race_id !== $race->id || $project->registration_category_id !== $category->id || ! $project->isProgressiveStage()) {
                throw new RegistrationRuleException('progressive_stage_not_current', "类别 {$category->name} 当前阶段配置无效。");
            }

            $groups = $this->normalizeProgressiveGroups($entry);
            if ($groups === []) {
                throw new RegistrationRuleException('empty_entries', "类别 {$category->name} 请至少选择一组。");
            }

            $normalizedGroups = [];
            $groupSignatures = [];
            $pigeonUsage = [];
            foreach ($groups as $groupIndex => $pigeonIds) {
                if (count($pigeonIds) !== (int) $project->group_size) {
                    throw new RegistrationRuleException('group_size_mismatch', "项目 {$project->name} 必须选择 {$project->group_size} 羽。");
                }

                $groupKey = $this->groupSignature($pigeonIds);
                if (isset($groupSignatures[$groupKey])) {
                    throw new RegistrationRuleException('duplicate_group', "项目 {$project->name} 已存在相同足环组合。");
                }
                $groupSignatures[$groupKey] = true;

                $entryPigeons = [];
                foreach ($pigeonIds as $pigeonId) {
                    $pigeon = $pigeons->get($pigeonId);
                    if (! $pigeon instanceof Pigeon) {
                        throw new RegistrationRuleException('pigeon_not_owned', '存在不属于当前会员或不可报名的足环。', 403);
                    }
                    if ((int) $pigeon->pigeon_library_id !== (int) $project->pigeon_library_id) {
                        throw new RegistrationRuleException('pigeon_not_owned', '存在不属于当前阶段足环库的足环。', 403);
                    }
                    $entryPigeons[] = $pigeon;
                    $pigeonUsage[$pigeonId] = ($pigeonUsage[$pigeonId] ?? 0) + 1;
                }

                $normalizedGroups[] = [
                    'pigeons' => $entryPigeons,
                    'group_key' => $groupKey,
                    'group_index' => $groupIndex + 1,
                ];
            }

            $this->assertProgressiveLimits($project, count($normalizedGroups), $pigeonUsage);
            $this->assertProgressiveEligibility($member, $category, $project, collect($normalizedGroups)->pluck('group_key')->all());

            $totalAmount += count($normalizedGroups) * $project->price_cent;
            $normalized[] = [
                'category' => $category,
                'project' => $project,
                'groups' => $normalizedGroups,
            ];
        }

        return ['entries' => $normalized, 'total_amount_cent' => $totalAmount];
    }

    private function assertRaceCanAccept(Race $race, int $configVersion): void
    {
        if (! $race->isOpenForRegistration()) {
            throw new RegistrationRuleException('race_not_open', '赛事当前不在报名时间内。', 409);
        }

        if ($race->config_version !== $configVersion) {
            throw new RegistrationRuleException('config_version_changed', '赛事报名项目已更新，请刷新页面后重新确认报名。', 409);
        }
    }

    private function assertProjectLimits(RaceProject $project, int $entryCount, array $pigeonUsage): void
    {
        if ($project->max_entries_per_member !== null && $entryCount > $project->max_entries_per_member) {
            throw new RegistrationRuleException('project_entry_limit_exceeded', "项目 {$project->name} 已超过每会员报名上限。");
        }

        foreach ($pigeonUsage as $usage) {
            if (! $project->allow_repeat_pigeon_in_project && $usage > 1) {
                throw new RegistrationRuleException('repeat_pigeon_not_allowed', "项目 {$project->name} 不允许同一足环重复进入多个组合。");
            }

            if ($project->max_usage_per_pigeon !== null && $usage > $project->max_usage_per_pigeon) {
                throw new RegistrationRuleException('pigeon_usage_limit_exceeded', "项目 {$project->name} 已超过每只足环最大使用次数。");
            }
        }
    }

    private function flattenPigeonIds(array $entries): array
    {
        return collect($entries)
            ->flatMap(fn (array $entry): array => $entry['pigeon_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function flattenProgressivePigeonIds(array $entries): array
    {
        return collect($entries)
            ->flatMap(fn (array $entry): array => collect($this->normalizeProgressiveGroups($entry))->flatten()->all())
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeProgressiveGroups(array $entry): array
    {
        if (isset($entry['groups']) && is_array($entry['groups'])) {
            return collect($entry['groups'])
                ->map(fn (array $group): array => array_values(array_unique(array_map('intval', $group['pigeon_ids'] ?? []))))
                ->filter(fn (array $pigeonIds): bool => $pigeonIds !== [])
                ->values()
                ->all();
        }

        $pigeonIds = array_values(array_unique(array_map('intval', $entry['pigeon_ids'] ?? [])));

        return collect($pigeonIds)
            ->map(fn (int $pigeonId): array => [$pigeonId])
            ->values()
            ->all();
    }

    private function assertProgressiveLimits(RaceProject $project, int $groupCount, array $pigeonUsage): void
    {
        if ($project->max_entries_per_member !== null && $groupCount > $project->max_entries_per_member) {
            throw new RegistrationRuleException('project_entry_limit_exceeded', "项目 {$project->name} 已超过每会员报名上限。");
        }

        foreach ($pigeonUsage as $usage) {
            if ($project->max_usage_per_pigeon !== null && $usage > $project->max_usage_per_pigeon) {
                throw new RegistrationRuleException('pigeon_usage_limit_exceeded', "项目 {$project->name} 已超过每只足环最大使用次数。");
            }
        }
    }

    private function assertProgressiveEligibility(Member $member, RegistrationCategory $category, RaceProject $project, array $groupKeys): void
    {
        if ((int) $project->stage_order <= 1) {
            return;
        }

        $previousProject = $category->stageProjects
            ->first(fn (RaceProject $candidate): bool => (int) $candidate->stage_order === (int) $project->stage_order - 1);

        if (! $previousProject instanceof RaceProject) {
            throw new RegistrationRuleException('previous_stage_not_confirmed', "类别 {$category->name} 缺少上一阶段确认数据。");
        }

        $eligible = ProgressiveStageEntry::query()
            ->where('member_id', $member->id)
            ->where('registration_category_id', $category->id)
            ->where('race_project_id', $previousProject->id)
            ->where('status', RegistrationStatus::Confirmed->value)
            ->get()
            ->groupBy(fn (ProgressiveStageEntry $entry): string => $entry->group_key ?: (string) $entry->pigeon_id)
            ->map(fn (Collection $group): string => $this->groupSignature($group->pluck('pigeon_id')->map(fn ($id): int => (int) $id)->all()))
            ->values()
            ->all();

        $missing = array_values(array_diff($groupKeys, $eligible));
        if ($missing !== []) {
            throw new RegistrationRuleException('progressive_pigeon_not_eligible', "类别 {$category->name} 只能选择上一阶段已确认足环组。");
        }
    }

    private function groupSignature(array $pigeonIds): string
    {
        sort($pigeonIds, SORT_NUMERIC);

        return implode(':', $pigeonIds);
    }

    private function makeRegistrationNo(Race $race, Member $member): string
    {
        $loftNumber = preg_replace('/\s+/', '', trim($member->loft_number));

        return "R{$race->id}-{$loftNumber}";
    }

    private function writeSnapshots(Registration $registration, array $entries): void
    {
        foreach ($entries as $entry) {
            /** @var RaceProject $project */
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

            $pigeonRows = collect($entry['pigeons'])->values()->map(fn (Pigeon $pigeon, int $order): array => [
                'registration_entry_id' => $snapshot->id,
                'pigeon_id' => $pigeon->id,
                'ring_number_snapshot' => $pigeon->ring_number,
                'sort_order' => $order + 1,
                'created_at' => now(),
            ])->all();

            RegistrationEntryPigeon::query()->insert($pigeonRows);
        }
    }

    private function writeProgressiveSnapshots(Registration $registration, array $entries, Race $race): void
    {
        foreach ($entries as $entry) {
            /** @var RegistrationCategory $category */
            $category = $entry['category'];
            /** @var RaceProject $project */
            $project = $entry['project'];
            $selectedGroupKeys = collect($entry['groups'])->pluck('group_key')->sort()->values()->all();
            $existing = ProgressiveStageEntry::query()
                ->where('member_id', $registration->member_id)
                ->where('registration_category_id', $category->id)
                ->where('race_project_id', $project->id)
                ->get();
            $existingGroupKeys = $existing
                ->groupBy(fn (ProgressiveStageEntry $row): string => $row->group_key ?: (string) $row->pigeon_id)
                ->map(fn (Collection $group): string => $this->groupSignature($group->pluck('pigeon_id')->map(fn ($id): int => (int) $id)->all()))
                ->sort()
                ->values();
            $keepConfirmed = $existingGroupKeys->all() === $selectedGroupKeys
                && $existing->isNotEmpty()
                && $existing->every(fn (ProgressiveStageEntry $row): bool => $row->status === RegistrationStatus::Confirmed);
            $status = $keepConfirmed || ! $race->require_admin_confirm
                ? RegistrationStatus::Confirmed
                : RegistrationStatus::PendingConfirmation;
            $confirmedAt = $status === RegistrationStatus::Confirmed ? ($existing->first()?->confirmed_at ?? now()) : null;
            $confirmedBy = $status === RegistrationStatus::Confirmed ? $existing->first()?->confirmed_by : null;

            ProgressiveStageEntry::query()
                ->where('member_id', $registration->member_id)
                ->where('registration_category_id', $category->id)
                ->where('race_project_id', $project->id)
                ->delete();

            foreach ($entry['groups'] as $groupIndex => $group) {
                foreach ($group['pigeons'] as $order => $pigeon) {
                    /** @var Pigeon $pigeon */
                    ProgressiveStageEntry::query()->create([
                        'registration_id' => $registration->id,
                        'race_id' => $registration->race_id,
                        'registration_category_id' => $category->id,
                        'race_project_id' => $project->id,
                        'member_id' => $registration->member_id,
                        'group_key' => $group['group_key'],
                        'group_index' => $groupIndex + 1,
                        'group_size_snapshot' => $project->group_size,
                        'pigeon_id' => $pigeon->id,
                        'pigeon_sort_order' => $order + 1,
                        'loft_number_snapshot' => $pigeon->loft_number,
                        'participant_name_snapshot' => $pigeon->participant_name,
                        'ring_number_snapshot' => $pigeon->ring_number,
                        'project_name_snapshot' => $project->name,
                        'price_cent_snapshot' => $project->price_cent,
                        'status' => $status->value,
                        'source' => ProgressiveStageEntry::SOURCE_MEMBER,
                        'submitted_at' => now(),
                        'confirmed_at' => $confirmedAt,
                        'confirmed_by' => $confirmedBy,
                    ]);
                }
            }
        }
    }
}
