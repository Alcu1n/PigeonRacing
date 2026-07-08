<?php
// [IN]: Race registrations, standard entries, progressive stage entries, and publication scope / 赛事报名、普通明细、递进阶段明细与发布范围
// [OUT]: Race-level read-only registration detail matrices with current progressive stages for member H5 / 会员端整场只读报名明细矩阵与当前递进阶段
// [POS]: Backend published race details read-model service / 后端已发布赛事明细读模型服务
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Models\ProgressiveStageEntry;
use App\Models\Race;
use App\Models\RaceProject;
use App\Models\Registration;
use App\Models\RegistrationEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PublishedRaceDetailsService
{
    public function payload(Race $race): array
    {
        $projects = $race->projects()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $standardEntries = $this->standardEntries($race);
        $progressiveEntries = $this->progressiveEntries($race);

        return [
            'race' => [
                'id' => $race->id,
                'name' => $race->name,
                'registration_end_at' => $race->registration_end_at?->toDateTimeString(),
            ],
            'published_at' => $race->registration_details_published_at?->toDateTimeString(),
            'scope' => $race->registration_details_scope ?: Race::DETAILS_SCOPE_CONFIRMED_ONLY,
            'scope_label' => $this->scopeLabel($race),
            'single' => $this->singleMatrix($projects, $standardEntries),
            'multi' => $this->multiGroups($standardEntries),
            'progressive' => $this->progressiveGroups($progressiveEntries),
        ];
    }

    private function standardEntries(Race $race): Collection
    {
        return RegistrationEntry::query()
            ->with([
                'registration.member',
                'pigeons' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            ])
            ->whereHas('registration', function (Builder $query) use ($race): void {
                $query->where('race_id', $race->id);
                $this->applyRegistrationScope($query, $race);
            })
            ->orderBy('race_project_id')
            ->orderBy('group_index')
            ->orderBy('id')
            ->get();
    }

    private function progressiveEntries(Race $race): Collection
    {
        return ProgressiveStageEntry::query()
            ->with(['member', 'category.currentStage', 'project'])
            ->where('race_id', $race->id)
            ->when(
                $race->registration_details_scope !== Race::DETAILS_SCOPE_ALL_SUBMITTED,
                fn (Builder $query) => $query->where('status', RegistrationStatus::Confirmed->value),
                fn (Builder $query) => $query->whereNotIn('status', [
                    RegistrationStatus::Draft->value,
                    RegistrationStatus::Cancelled->value,
                    RegistrationStatus::Voided->value,
                ]),
            )
            ->orderBy('registration_category_id')
            ->orderBy('race_project_id')
            ->orderBy('member_id')
            ->orderBy('group_index')
            ->orderBy('pigeon_sort_order')
            ->orderBy('id')
            ->get();
    }

    private function singleMatrix(Collection $projects, Collection $entries): array
    {
        $singleEntries = $entries->where('group_size_snapshot', 1)->values();
        $projectIds = $singleEntries->pluck('race_project_id')->unique()->values();
        $singleProjects = $projects
            ->whereIn('id', $projectIds->all())
            ->values()
            ->map(fn (RaceProject $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'sort_order' => (int) $project->sort_order,
            ])
            ->all();

        $rows = [];

        foreach ($singleEntries as $entry) {
            /** @var RegistrationEntry $entry */
            $registration = $entry->registration;
            $member = $registration?->member;
            $pigeon = $entry->pigeons->first();

            if (! $registration || ! $pigeon) {
                continue;
            }

            $rowKey = $registration->member_id.':'.$pigeon->pigeon_id;
            $rows[$rowKey] ??= [
                'loft_number' => $member?->loft_number ?? '',
                'participant_name' => $member?->participant_name ?? '',
                'ring_number' => $pigeon->ring_number_snapshot,
                'selected_projects' => [],
            ];
            $rows[$rowKey]['selected_projects'][(string) $entry->race_project_id] = $registration->status->value;
        }

        return [
            'projects' => $singleProjects,
            'rows' => collect($rows)
                ->sortBy([['loft_number', 'asc'], ['ring_number', 'asc']])
                ->values()
                ->all(),
        ];
    }

    private function multiGroups(Collection $entries): array
    {
        return $entries
            ->where('group_size_snapshot', '>', 1)
            ->groupBy('race_project_id')
            ->map(function (Collection $projectEntries): array {
                /** @var RegistrationEntry $first */
                $first = $projectEntries->first();

                return [
                    'project_id' => $first->race_project_id,
                    'project_name' => $first->project_name_snapshot,
                    'group_size' => (int) $first->group_size_snapshot,
                    'groups' => $projectEntries
                        ->sortBy(fn (RegistrationEntry $entry): string => ($entry->registration?->member?->loft_number ?? '').':'.str_pad((string) $entry->group_index, 6, '0', STR_PAD_LEFT))
                        ->map(function (RegistrationEntry $entry): array {
                            $registration = $entry->registration;
                            $member = $registration?->member;

                            return [
                                'loft_number' => $member?->loft_number ?? '',
                                'participant_name' => $member?->participant_name ?? '',
                                'group_index' => (int) $entry->group_index,
                                'status' => $registration?->status->value ?? RegistrationStatus::PendingConfirmation->value,
                                'rings' => $entry->pigeons
                                    ->sortBy('sort_order')
                                    ->pluck('ring_number_snapshot')
                                    ->filter()
                                    ->values()
                                    ->all(),
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function progressiveGroups(Collection $entries): array
    {
        return $entries
            ->groupBy('registration_category_id')
            ->map(function (Collection $categoryEntries): ?array {
                /** @var ProgressiveStageEntry $firstCategory */
                $firstCategory = $categoryEntries->first();
                $currentStageId = (int) ($firstCategory->category?->current_stage_project_id ?? 0);
                if ($currentStageId <= 0) {
                    return null;
                }

                $currentStageEntries = $categoryEntries
                    ->where('race_project_id', $currentStageId)
                    ->values();

                if ($currentStageEntries->isEmpty()) {
                    return null;
                }

                return [
                    'category_id' => $firstCategory->registration_category_id,
                    'category_name' => $firstCategory->category?->name ?? '递进报名',
                    'stages' => $currentStageEntries
                        ->groupBy('race_project_id')
                        ->map(function (Collection $stageEntries): array {
                            /** @var ProgressiveStageEntry $firstStage */
                            $firstStage = $stageEntries->first();

                            return [
                                'stage_project_id' => $firstStage->race_project_id,
                                'stage_project_name' => $firstStage->project_name_snapshot,
                                'group_size' => (int) $firstStage->group_size_snapshot,
                                'groups' => $stageEntries
                                    ->groupBy(fn (ProgressiveStageEntry $entry): string => $entry->member_id.':'.($entry->group_key ?: $entry->pigeon_id))
                                    ->map(function (Collection $groupEntries): array {
                                        /** @var ProgressiveStageEntry $first */
                                        $first = $groupEntries->sortBy('pigeon_sort_order')->first();

                                        return [
                                            'loft_number' => $first->member?->loft_number ?? $first->loft_number_snapshot,
                                            'participant_name' => $first->member?->participant_name ?? $first->participant_name_snapshot,
                                            'group_index' => (int) $first->group_index,
                                            'status' => $groupEntries->every(fn (ProgressiveStageEntry $entry): bool => $entry->status === $first->status)
                                                ? $first->status->value
                                                : RegistrationStatus::PendingConfirmation->value,
                                            'rings' => $groupEntries
                                                ->sortBy('pigeon_sort_order')
                                                ->pluck('ring_number_snapshot')
                                                ->filter()
                                                ->values()
                                                ->all(),
                                        ];
                                    })
                                    ->sortBy([['loft_number', 'asc'], ['group_index', 'asc']])
                                    ->values()
                                    ->all(),
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function applyRegistrationScope(Builder $query, Race $race): void
    {
        if ($race->registration_details_scope !== Race::DETAILS_SCOPE_ALL_SUBMITTED) {
            $query->where('status', RegistrationStatus::Confirmed->value);

            return;
        }

        $query->whereNotIn('status', [
            RegistrationStatus::Draft->value,
            RegistrationStatus::Cancelled->value,
            RegistrationStatus::Voided->value,
        ]);
    }

    private function scopeLabel(Race $race): string
    {
        return $race->registration_details_scope === Race::DETAILS_SCOPE_ALL_SUBMITTED ? '全部提交' : '仅已确认';
    }
}
