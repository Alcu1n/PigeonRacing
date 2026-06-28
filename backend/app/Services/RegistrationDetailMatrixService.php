<?php
// [IN]: Registration snapshot entries, pigeon snapshots, and project sort rows / 报名项目快照、足环快照与项目排序行
// [OUT]: Ring-first single-pigeon matrix and multi-pigeon group tables / 足环优先的单羽矩阵与多羽组表格
// [POS]: Backend registration detail view-shaping service / 后端报名详情展示整形服务
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Services;

use App\Models\Registration;
use App\Models\RegistrationEntry;
use App\Models\RaceProject;
use Illuminate\Support\Collection;

class RegistrationDetailMatrixService
{
    public function matrix(Registration $registration): array
    {
        $registration->loadMissing(['entries.pigeons']);

        $entries = $registration->entries
            ->sortBy([
                ['group_size_snapshot', 'asc'],
                ['project_name_snapshot', 'asc'],
                ['group_index', 'asc'],
            ])
            ->values();

        return [
            'single' => $this->singleMatrix($entries->where('group_size_snapshot', 1)->values()),
            'multi' => $this->multiGroups($entries->where('group_size_snapshot', '>', 1)->values()),
        ];
    }

    private function singleMatrix(Collection $entries): array
    {
        $sortOrders = $this->projectSortOrders($entries);
        $projects = $entries
            ->groupBy('race_project_id')
            ->map(function (Collection $projectEntries, int|string $projectId) use ($sortOrders): array {
                $first = $projectEntries->first();

                return [
                    'key' => (string) $projectId,
                    'project_name' => $first->project_name_snapshot,
                    'price_cent' => (int) $first->price_cent_snapshot,
                    'sort_order' => $sortOrders[(int) $projectId] ?? PHP_INT_MAX,
                ];
            })
            ->sortBy([
                ['sort_order', 'asc'],
                ['project_name', 'asc'],
            ])
            ->values();

        $rings = $entries
            ->flatMap(fn (RegistrationEntry $entry): Collection => $this->ringNumbers($entry))
            ->unique()
            ->sort()
            ->values();

        $rows = $rings
            ->map(function (string $ring) use ($entries, $projects): array {
                $selectedProjects = $entries
                    ->filter(fn (RegistrationEntry $entry): bool => $this->ringNumbers($entry)->contains($ring))
                    ->mapWithKeys(fn (RegistrationEntry $entry): array => [(string) $entry->race_project_id => true])
                    ->all();
                $amountCent = $projects
                    ->whereIn('key', array_keys($selectedProjects))
                    ->sum('price_cent');

                return [
                    'ring_number' => $ring,
                    'selected_projects' => $selectedProjects,
                    'count' => count($selectedProjects),
                    'amount_cent' => $amountCent,
                ];
            })
            ->values()
            ->all();

        return [
            'projects' => $projects->all(),
            'rows' => $rows,
            'total_count' => collect($rows)->sum('count'),
            'total_amount_cent' => collect($rows)->sum('amount_cent'),
        ];
    }

    private function multiGroups(Collection $entries): array
    {
        $sortOrders = $this->projectSortOrders($entries);

        return $entries
            ->groupBy('race_project_id')
            ->map(function (Collection $projectEntries, int|string $projectId) use ($sortOrders): array {
                $first = $projectEntries->first();
                $groups = $projectEntries
                    ->sortBy('group_index')
                    ->values()
                    ->map(fn (RegistrationEntry $entry): array => [
                        'group_index' => (int) $entry->group_index,
                        'rings' => $this->ringNumbers($entry)->values()->all(),
                    ])
                    ->all();

                return [
                    'project_name' => $first->project_name_snapshot,
                    'group_size' => (int) $first->group_size_snapshot,
                    'price_cent' => (int) $first->price_cent_snapshot,
                    'sort_order' => $sortOrders[(int) $projectId] ?? PHP_INT_MAX,
                    'groups' => $groups,
                    'group_count' => count($groups),
                    'amount_cent' => count($groups) * (int) $first->price_cent_snapshot,
                ];
            })
            ->sortBy([
                ['sort_order', 'asc'],
                ['project_name', 'asc'],
            ])
            ->values()
            ->all();
    }

    private function ringNumbers(RegistrationEntry $entry): Collection
    {
        return $entry->pigeons
            ->sortBy('sort_order')
            ->pluck('ring_number_snapshot')
            ->filter()
            ->values();
    }

    private function projectSortOrders(Collection $entries): array
    {
        $ids = $entries
            ->pluck('race_project_id')
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return RaceProject::query()
            ->whereKey($ids->all())
            ->pluck('sort_order', 'id')
            ->map(fn ($sortOrder): int => (int) $sortOrder)
            ->all();
    }
}
