<?php
// [IN]: Progressive category first stage project / 递进类别第一阶段项目
// [OUT]: Downloadable first-stage import template rows / 可下载第一阶段导入模板行
// [POS]: Backend progressive stage import template export / 后端递进阶段模板导出
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProgressiveStageImportTemplateExport implements FromArray, WithHeadings
{
    public function __construct(private readonly string $stageName, private readonly int $groupSize = 1) {}

    public function headings(): array
    {
        return ['序号', '会员棚号', '会员参赛名', '足环号码', $this->stageName];
    }

    public function array(): array
    {
        $firstRingGroup = collect(range(1, max(1, $this->groupSize)))
            ->map(fn (int $index): string => '2025-13-'.str_pad((string) $index, 6, '0', STR_PAD_LEFT))
            ->implode('，');
        $secondRingGroup = collect(range(max(1, $this->groupSize) + 1, max(1, $this->groupSize) * 2))
            ->map(fn (int $index): string => '2025-13-'.str_pad((string) $index, 6, '0', STR_PAD_LEFT))
            ->implode('，');

        return [
            [1, 'A001', '张三鸽舍', $firstRingGroup, '✓'],
            [2, 'A001', '张三鸽舍', $secondRingGroup, ''],
        ];
    }
}
