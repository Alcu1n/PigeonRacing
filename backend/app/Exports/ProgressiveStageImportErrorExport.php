<?php
// [IN]: Failed progressive stage import preview rows / 失败递进阶段导入预览行
// [OUT]: Downloadable progressive import error report / 可下载递进阶段导入错误报告
// [POS]: Backend progressive stage import error export / 后端递进阶段错误导出
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProgressiveStageImportErrorExport implements FromArray, WithHeadings
{
    public function __construct(private readonly array $rows) {}

    public function headings(): array
    {
        return ['行号', '序号', '会员棚号', 'Excel 参赛名', '系统参赛名', '足环号码', '阶段标记', '错误原因'];
    }

    public function array(): array
    {
        return collect($this->rows)->map(fn (array $row): array => [
            $row['line'],
            $row['data']['sequence'],
            $row['data']['loft_number'],
            $row['data']['participant_name'],
            $row['system_participant_name'] ?? '',
            $row['data']['ring_number'],
            $row['data']['stage_marker'],
            implode('；', $row['errors']),
        ])->values()->all();
    }
}
