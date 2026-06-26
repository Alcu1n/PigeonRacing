<?php
// [IN]: Race registrations with project and pigeon snapshots / 含项目与足环快照的赛事报名
// [OUT]: Matrix export with single-pigeon rows and unique multi-group rows / 单羽足环行与唯一多羽组合行矩阵导出
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
            ->with([
                'member',
                'entries' => fn ($query) => $query->orderBy('group_index')->orderBy('id'),
                'entries.pigeons' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            ])
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get();

        foreach ($registrations as $registration) {
            $member = $registration->member;
            $singleRows = [];
            $multiRows = [];

            foreach ($registration->entries as $entry) {
                $ringNumbers = $entry->pigeons
                    ->pluck('ring_number_snapshot')
                    ->filter()
                    ->values()
                    ->all();

                if ($entry->group_size_snapshot > 1) {
                    $ringText = implode('，', $ringNumbers);
                    $row = $this->rowTemplate($member?->loft_number ?? '', $member?->participant_name ?? '', $ringText);
                    $row['projects'][$entry->race_project_id] = $ringText;
                    $multiRows[] = $row;

                    continue;
                }

                foreach ($entry->pigeons as $pigeon) {
                    $key = "{$registration->id}:{$pigeon->pigeon_id}";
                    $singleRows[$key] ??= $this->rowTemplate(
                        $member?->loft_number ?? '',
                        $member?->participant_name ?? '',
                        $pigeon->ring_number_snapshot,
                    );
                    $singleRows[$key]['projects'][$entry->race_project_id] = '✓';
                }
            }

            array_push($rows, ...array_values($singleRows), ...$multiRows);
        }

        return collect($rows)->values()->map(function (array $row, int $index): array {
            return [
                $index + 1,
                $row['loft_number'],
                $row['participant_name'],
                $row['ring_number'],
                ...$this->projects->map(fn ($project): string => $row['projects'][$project->id] ?? '')->all(),
            ];
        });
    }

    private function rowTemplate(string $loftNumber, string $participantName, string $ringNumber): array
    {
        return [
            'loft_number' => $loftNumber,
            'participant_name' => $participantName,
            'ring_number' => $ringNumber,
            'projects' => array_fill_keys($this->projects->pluck('id')->all(), ''),
        ];
    }

    public function fileName(): string
    {
        $name = preg_replace('/[\\\\\\/\\:\\*\\?\\"\\<\\>\\|]+/', '-', $this->race->name);

        return '报名明细-'.$name.'-'.now()->format('YmdHis').'.xlsx';
    }
}
