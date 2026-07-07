<?php
// [IN]: Race registrations with project and pigeon snapshots / 含项目与足环快照的赛事报名
// [OUT]: Styled matrix export with race summary, single rows, unique multi-group rows, and progressive rows / 带赛事摘要、单羽行、唯一多羽组合行与递进行的样式化矩阵导出
// [POS]: Backend registration Excel export / 后端报名 Excel 导出
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Exports;

use App\Models\Race;
use App\Models\ProgressiveStageEntry;
use App\Models\RegistrationEntry;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RegistrationMatrixExport implements FromCollection, ShouldAutoSize, WithColumnWidths, WithCustomStartCell, WithEvents, WithHeadings
{
    private const TABLE_START_ROW = 5;

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
        $exportedProgressiveEntryIds = [];

        $registrations = $this->race->registrations()
            ->with([
                'member',
                'entries' => fn ($query) => $query->orderBy('group_index')->orderBy('id'),
                'entries.pigeons' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'progressiveStageEntries' => fn ($query) => $query->orderBy('race_project_id')->orderBy('group_index')->orderBy('pigeon_sort_order'),
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
                    $row = $this->rowTemplate($member?->loft_number ?? '', $member?->participant_name ?? '', '');
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

            foreach ($this->progressiveEntryGroups($registration->progressiveStageEntries) as $group) {
                array_push($exportedProgressiveEntryIds, ...$group['entry_ids']);
                if ($group['group_size'] > 1) {
                    $row = $this->rowTemplate($member?->loft_number ?? '', $member?->participant_name ?? '', '');
                    $row['projects'][$group['project_id']] = implode('，', $group['rings']);
                    $multiRows[] = $row;

                    continue;
                }

                $key = "{$registration->id}:progressive:{$group['project_id']}:{$group['pigeon_ids'][0]}";
                $singleRows[$key] ??= $this->rowTemplate(
                    $member?->loft_number ?? '',
                    $member?->participant_name ?? '',
                    $group['rings'][0] ?? '',
                );
                $singleRows[$key]['projects'][$group['project_id']] = '✓';
            }

            array_push($rows, ...array_values($singleRows), ...$multiRows);
        }

        $orphanProgressiveRows = [];
        $orphanProgressiveEntries = ProgressiveStageEntry::query()
            ->with(['member'])
            ->where('race_id', $this->race->id)
            ->when($exportedProgressiveEntryIds !== [], fn ($query) => $query->whereNotIn('id', $exportedProgressiveEntryIds))
            ->orderBy('loft_number_snapshot')
            ->orderBy('ring_number_snapshot')
            ->orderBy('race_project_id')
            ->get();

        foreach ($this->progressiveEntryGroups($orphanProgressiveEntries) as $group) {
            $entry = $group['first'];
            $member = $entry->member;
            if ($group['group_size'] > 1) {
                $row = $this->rowTemplate(
                    $member?->loft_number ?? $entry->loft_number_snapshot,
                    $member?->participant_name ?? $entry->participant_name_snapshot,
                    '',
                );
                $row['projects'][$group['project_id']] = implode('，', $group['rings']);
                $orphanProgressiveRows[] = $row;

                continue;
            }

            $key = "progressive-orphan:{$entry->member_id}:{$group['pigeon_ids'][0]}";
            $orphanProgressiveRows[$key] ??= $this->rowTemplate(
                $member?->loft_number ?? $entry->loft_number_snapshot,
                $member?->participant_name ?? $entry->participant_name_snapshot,
                $group['rings'][0] ?? '',
            );
            $orphanProgressiveRows[$key]['projects'][$group['project_id']] = '✓';
        }

        array_push($rows, ...array_values($orphanProgressiveRows));

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

    public function startCell(): string
    {
        return 'A'.self::TABLE_START_ROW;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 14,
            'C' => 16,
            'D' => 26,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $this->styleSheet($event->sheet->getDelegate());
            },
        ];
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

    private function progressiveEntryGroups(Collection $entries): array
    {
        return $entries
            ->groupBy(fn (ProgressiveStageEntry $entry): string => $entry->race_project_id.':'.($entry->group_key ?: $entry->pigeon_id))
            ->map(function (Collection $group): array {
                /** @var ProgressiveStageEntry $first */
                $first = $group->sortBy('pigeon_sort_order')->first();

                return [
                    'first' => $first,
                    'entry_ids' => $group->pluck('id')->all(),
                    'project_id' => $first->race_project_id,
                    'group_size' => (int) $first->group_size_snapshot,
                    'pigeon_ids' => $group->sortBy('pigeon_sort_order')->pluck('pigeon_id')->all(),
                    'rings' => $group->sortBy('pigeon_sort_order')->pluck('ring_number_snapshot')->filter()->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function styleSheet(Worksheet $sheet): void
    {
        $lastColumn = Coordinate::stringFromColumnIndex(4 + $this->projects->count());
        $lastRow = max(self::TABLE_START_ROW + $this->collection()->count(), self::TABLE_START_ROW);
        $tableHeaderRange = "A".self::TABLE_START_ROW.":{$lastColumn}".self::TABLE_START_ROW;
        $usedRange = "A1:{$lastColumn}{$lastRow}";

        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->mergeCells("A3:{$lastColumn}3");

        $sheet->setCellValue('A1', '赛事名称：'.$this->race->name);
        $sheet->setCellValue('A2', '报名截止时间：'.$this->race->registration_end_at?->toDateTimeString());
        $sheet->setCellValue('A3', '项目数量统计：'.$this->projectSummaryText());

        $sheet->getStyle('A1:A3')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'EAF7EF'],
            ],
        ]);

        $sheet->getStyle($tableHeaderRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '116B43'],
            ],
        ]);

        $sheet->getStyle($usedRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '4B5563'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->getRowDimension(2)->setRowHeight(22);
        $sheet->getRowDimension(3)->setRowHeight(34);
        $sheet->freezePane('A'.(self::TABLE_START_ROW + 1));
    }

    private function projectSummaryText(): string
    {
        $standardCounts = RegistrationEntry::query()
            ->whereHas('registration', fn ($query) => $query->where('race_id', $this->race->id))
            ->selectRaw('race_project_id, count(*) as total')
            ->groupBy('race_project_id')
            ->pluck('total', 'race_project_id');
        $progressiveCounts = ProgressiveStageEntry::query()
            ->where('race_id', $this->race->id)
            ->get(['race_project_id', 'member_id', 'group_key', 'pigeon_id'])
            ->groupBy('race_project_id')
            ->map(fn (Collection $entries): int => $entries
                ->groupBy(fn (ProgressiveStageEntry $entry): string => $entry->member_id.':'.($entry->group_key ?: $entry->pigeon_id))
                ->count());

        return $this->projects
            ->map(fn ($project): string => $project->name.'：'.((int) ($standardCounts[$project->id] ?? 0) + (int) ($progressiveCounts[$project->id] ?? 0)))
            ->implode('，');
    }

    public function fileName(): string
    {
        $name = preg_replace('/[\\\\\\/\\:\\*\\?\\"\\<\\>\\|]+/', '-', $this->race->name);

        return '报名明细-'.$name.'-'.now()->format('YmdHis').'.xlsx';
    }
}
