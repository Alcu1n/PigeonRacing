<?php
// [IN]: Race, project, member, and pigeon read models / 赛事、项目、会员与足环读取模型
// [OUT]: Versioned cached bootstrap payloads and race/member invalidation hooks / 带版本的已缓存初始化数据与赛事/会员失效钩子
// [POS]: Backend read-cache coordinator / 后端读取缓存协调器
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Models\Member;
use App\Models\ProgressiveStageEntry;
use App\Models\Race;
use App\Models\RaceProject;
use App\Models\RegistrationCategory;
use Illuminate\Support\Facades\Cache;

class RaceCacheService
{
    public function bootstrap(Race $race, Member $member): array
    {
        return Cache::remember(
            $this->bootstrapKey($race->id, $member->id, $race->config_version),
            now()->addMinutes(5),
            fn (): array => $this->buildBootstrap($race->fresh(['projects']), $member->fresh())
        );
    }

    public function forgetRace(Race $race): void
    {
        $this->forgetRaceById($race->id);
    }

    public function forgetRaceById(int $raceId): void
    {
        Cache::forget($this->raceConfigKey($raceId));
        $configVersion = Race::query()->whereKey($raceId)->value('config_version');
        if ($configVersion !== null) {
            Cache::forget($this->raceConfigKey($raceId, (int) $configVersion));
        }

        Member::query()
            ->pluck('id')
            ->each(function (int $memberId) use ($raceId, $configVersion): void {
                Cache::forget($this->bootstrapKey($raceId, $memberId));
                if ($configVersion !== null) {
                    Cache::forget($this->bootstrapKey($raceId, $memberId, (int) $configVersion));
                }
            });
    }

    public function forgetMemberPigeons(Member $member): void
    {
        $this->forgetMemberPigeonsById($member->id);
    }

    public function forgetMemberPigeonsById(int $memberId): void
    {
        Cache::forget($this->memberPigeonsKey($memberId));

        Race::query()
            ->get(['id', 'config_version'])
            ->each(function (Race $race) use ($memberId): void {
                Cache::forget($this->bootstrapKey($race->id, $memberId));
                Cache::forget($this->bootstrapKey($race->id, $memberId, $race->config_version));
            });
    }

    public function forgetBootstrap(Race $race, Member $member): void
    {
        Cache::forget($this->bootstrapKey($race->id, $member->id));
        Cache::forget($this->bootstrapKey($race->id, $member->id, $race->config_version));
    }

    private function buildBootstrap(Race $race, Member $member): array
    {
        $projects = Cache::remember(
            $this->raceConfigKey($race->id, $race->config_version),
            now()->addMinutes(15),
            fn () => $race->projects()
                ->where('is_enabled', true)
                ->where('project_type', RaceProject::TYPE_STANDARD)
                ->orderBy('sort_order')
                ->get(['id', 'race_id', 'name', 'group_size', 'price_cent', 'description', 'sort_order', 'is_enabled', 'allow_repeat_pigeon_in_project', 'max_entries_per_member', 'max_usage_per_pigeon'])
                ->values()
        );

        $pigeons = Cache::remember(
            $this->memberPigeonsKey($member->id),
            now()->addMinutes(10),
            fn () => $member->pigeons()
                ->where('status', 'normal')
                ->orderBy('ring_number')
                ->get(['id', 'ring_number'])
                ->values()
        );

        $existing = $member->registrations()
            ->with(['entries.pigeons', 'progressiveStageEntries.category'])
            ->where('race_id', $race->id)
            ->latest('submitted_at')
            ->first();

        return [
            'race' => [
                'id' => $race->id,
                'name' => $race->name,
                'description' => $race->description,
                'registration_start_at' => optional($race->registration_start_at)->toDateTimeString(),
                'registration_end_at' => optional($race->registration_end_at)->toDateTimeString(),
                'status' => $race->isOpenForRegistration() ? 'open' : $race->status->value,
                'config_version' => $race->config_version,
                'allow_member_edit' => $race->allow_member_edit,
            ],
            'member' => [
                'id' => $member->id,
                'loft_number' => $member->loft_number,
                'participant_name' => $member->participant_name,
            ],
            'projects' => $projects,
            'pigeons' => $pigeons,
            'progressive_categories' => $this->progressiveCategories($race, $member, $pigeons),
            'existing_registration' => $existing ? $this->serializeRegistration($existing) : null,
        ];
    }

    public function serializeRegistration($registration): array
    {
        return [
            'id' => $registration->id,
            'registration_no' => $registration->registration_no,
            'status' => $registration->status->value,
            'total_amount_cent' => $registration->total_amount_cent,
            'submitted_at' => optional($registration->submitted_at)->toDateTimeString(),
            'entries' => $registration->entries->map(fn ($entry): array => [
                'project_id' => $entry->race_project_id,
                'project_name' => $entry->project_name_snapshot,
                'group_size' => $entry->group_size_snapshot,
                'price_cent' => $entry->price_cent_snapshot,
                'group_index' => $entry->group_index,
                'pigeons' => $entry->pigeons->map(fn ($pigeon): array => [
                    'pigeon_id' => $pigeon->pigeon_id,
                    'ring_number' => $pigeon->ring_number_snapshot,
                    'sort_order' => $pigeon->sort_order,
                ])->values(),
            ])->values(),
            'progressive_entries' => $registration->progressiveStageEntries->map(fn (ProgressiveStageEntry $entry): array => [
                'category_id' => $entry->registration_category_id,
                'category_name' => $entry->category?->name,
                'stage_project_id' => $entry->race_project_id,
                'stage_project_name' => $entry->project_name_snapshot,
                'group_key' => $entry->group_key,
                'group_index' => $entry->group_index,
                'group_size' => $entry->group_size_snapshot,
                'pigeon_id' => $entry->pigeon_id,
                'ring_number' => $entry->ring_number_snapshot,
                'pigeon_sort_order' => $entry->pigeon_sort_order,
                'price_cent' => $entry->price_cent_snapshot,
                'status' => $entry->status->value,
                'submitted_at' => optional($entry->submitted_at)->toDateTimeString(),
            ])->values(),
        ];
    }

    private function progressiveCategories(Race $race, Member $member, $pigeons): array
    {
        $pigeonsById = $pigeons->keyBy('id');

        return RegistrationCategory::query()
            ->with(['currentStage', 'stageProjects'])
            ->where('race_id', $race->id)
            ->where('type', RegistrationCategory::TYPE_PROGRESSIVE)
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (RegistrationCategory $category) use ($member, $pigeons, $pigeonsById): array {
                $currentStage = $category->currentStage;
                $eligibleGroups = collect();
                $selected = collect();
                $selectedStatus = null;

                if ($currentStage instanceof RaceProject && $currentStage->is_enabled && $currentStage->isProgressiveStage()) {
                    $eligibleGroups = $this->eligibleProgressiveGroups($category, $currentStage, $member, $pigeons, $pigeonsById);
                    $selected = ProgressiveStageEntry::query()
                        ->where('member_id', $member->id)
                        ->where('registration_category_id', $category->id)
                        ->where('race_project_id', $currentStage->id)
                        ->get();
                    $selectedStatus = $selected->isEmpty()
                        ? null
                        : ($selected->every(fn (ProgressiveStageEntry $entry): bool => $entry->status === RegistrationStatus::Confirmed)
                            ? RegistrationStatus::Confirmed->value
                            : RegistrationStatus::PendingConfirmation->value);
                }

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'sort_order' => $category->sort_order,
                    'current_stage' => $currentStage instanceof RaceProject ? [
                        'id' => $currentStage->id,
                        'name' => $currentStage->name,
                        'price_cent' => $currentStage->price_cent,
                        'group_size' => $currentStage->group_size,
                        'stage_order' => $currentStage->stage_order,
                        'sort_order' => $currentStage->sort_order,
                    ] : null,
                    'eligible_groups' => $eligibleGroups->values()->all(),
                    'eligible_pigeons' => $eligibleGroups
                        ->flatMap(fn (array $group): array => $group['pigeons'])
                        ->unique('id')
                        ->values()
                        ->all(),
                    'selected_groups' => $this->progressiveEntryGroups($selected)->values()->all(),
                    'selected_pigeon_ids' => $selected->pluck('pigeon_id')->map(fn ($id): int => (int) $id)->values()->all(),
                    'status' => $selectedStatus,
                ];
            })
            ->values()
            ->all();
    }

    private function eligibleProgressiveGroups(RegistrationCategory $category, RaceProject $currentStage, Member $member, $pigeons, $pigeonsById)
    {
        if ((int) $currentStage->stage_order <= 1) {
            if ((int) $currentStage->group_size > 1) {
                return $this->confirmedProgressiveGroups($member, $category, $currentStage, $pigeonsById);
            }

            return $pigeons->map(fn ($pigeon, int $index): array => [
                'group_key' => (string) $pigeon->id,
                'group_index' => $index + 1,
                'pigeon_ids' => [(int) $pigeon->id],
                'pigeons' => [[
                    'id' => (int) $pigeon->id,
                    'ring_number' => $pigeon->ring_number,
                    'sort_order' => 1,
                ]],
            ]);
        }

        $previousStage = $category->stageProjects
            ->first(fn (RaceProject $project): bool => (int) $project->stage_order === (int) $currentStage->stage_order - 1);

        if (! $previousStage instanceof RaceProject) {
            return collect();
        }

        return $this->confirmedProgressiveGroups($member, $category, $previousStage, $pigeonsById);
    }

    private function confirmedProgressiveGroups(Member $member, RegistrationCategory $category, RaceProject $stage, $pigeonsById)
    {
        return $this->progressiveEntryGroups(
            ProgressiveStageEntry::query()
                ->where('member_id', $member->id)
                ->where('registration_category_id', $category->id)
                ->where('race_project_id', $stage->id)
                ->where('status', RegistrationStatus::Confirmed->value)
                ->orderBy('group_index')
                ->orderBy('pigeon_sort_order')
                ->get()
            ->filter(fn (ProgressiveStageEntry $entry): bool => $pigeonsById->has($entry->pigeon_id))
        );
    }

    private function progressiveEntryGroups($entries)
    {
        return $entries
            ->groupBy(fn (ProgressiveStageEntry $entry): string => $entry->group_key ?: (string) $entry->pigeon_id)
            ->map(function ($groupEntries): array {
                $first = $groupEntries->sortBy('pigeon_sort_order')->first();
                $pigeons = $groupEntries
                    ->sortBy('pigeon_sort_order')
                    ->map(fn (ProgressiveStageEntry $entry): array => [
                        'id' => (int) $entry->pigeon_id,
                        'ring_number' => $entry->ring_number_snapshot,
                        'sort_order' => (int) $entry->pigeon_sort_order,
                    ])
                    ->values()
                    ->all();

                return [
                    'group_key' => $first->group_key ?: (string) $first->pigeon_id,
                    'group_index' => (int) $first->group_index,
                    'pigeon_ids' => collect($pigeons)->pluck('id')->all(),
                    'pigeons' => $pigeons,
                ];
            })
            ->sortBy('group_index')
            ->values();
    }

    private function raceConfigKey(int $raceId, ?int $configVersion = null): string
    {
        if ($configVersion !== null) {
            return "race:{$raceId}:version:{$configVersion}:config";
        }

        return "race:{$raceId}:config";
    }

    private function memberPigeonsKey(int $memberId): string
    {
        return "member:{$memberId}:pigeons";
    }

    private function bootstrapKey(int $raceId, int $memberId, ?int $configVersion = null): string
    {
        if ($configVersion !== null) {
            return "race:{$raceId}:version:{$configVersion}:member:{$memberId}:bootstrap";
        }

        return "race:{$raceId}:member:{$memberId}:bootstrap";
    }
}
