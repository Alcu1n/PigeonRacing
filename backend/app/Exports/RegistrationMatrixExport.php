<?php
// [IN]: Race registrations with project and pigeon snapshots / 含项目与足环快照的赛事报名
// [OUT]: Matrix-shaped registration detail export / 矩阵形报名明细导出
// [POS]: Backend registration Excel export / 后端报名 Excel 导出
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Exports;

use App\Models\Race;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RegistrationMatrixExport implements FromCollection, WithHeadings
{
    private Race $race;

    private Collection $projects;

    public function __construct(private readonly int $raceId)
    {
        $this->race = Race::query()->findOrFail($this->raceId);
        $this->projects = $this->race->projects()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name']);
    }

    public function headings(): array
    {
        return [
            '序号',
            '会员棚号',
            '会员参赛名',
            '足环号码',
            ...$this->projects->pluck('name')->all(),
        ];
    }

    public function collection(): Collection
    {
        $rows = [];

        $registrations = $this->race->registrations()
            ->with(['member', 'entries.pigeons'])
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get();

        foreach ($registrations as $registration) {
            $member = $registration->member;

            foreach ($registration->entries as $entry) {
                foreach ($entry->pigeons as $pigeon) {
                    $key = "{$registration->id}:{$pigeon->pigeon_id}";
                    $rows[$key] ??= [
                        'loft_number' => $member?->loft_number ?? '',
                        'participant_name' => $member?->participant_name ?? '',
                        'ring_number' => $pigeon->ring_number_snapshot,
                        'projects' => array_fill_keys($this->projects->pluck('id')->all(), []),
                    ];

                    $mark = $entry->group_size_snapshot === 1 ? '✓' : "第{$entry->group_index}组";
                    $rows[$key]['projects'][$entry->race_project_id][] = $mark;
                }
            }
        }

        return collect(array_values($rows))->values()->map(function (array $row, int $index): array {
            return [
                $index + 1,
                $row['loft_number'],
                $row['participant_name'],
                $row['ring_number'],
                ...$this->projects->map(fn ($project): string => implode('，', array_unique($row['projects'][$project->id] ?? [])))->all(),
            ];
        });
    }

    public function fileName(): string
    {
        $name = preg_replace('/[\\\\\\/\\:\\*\\?\\"\\<\\>\\|]+/', '-', $this->race->name);

        return '报名明细-'.$name.'-'.now()->format('YmdHis').'.xlsx';
    }
}
