<?php
// [IN]: Failed pigeon import preview rows / 失败足环导入预览行
// [OUT]: Downloadable import error report / 可下载导入错误报告
// [POS]: Backend pigeon import error export / 后端足环导入错误导出
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PigeonImportErrorExport implements FromArray, WithHeadings
{
    public function __construct(private readonly array $rows) {}

    public function headings(): array
    {
        return ['行号', '序号', '会员棚号', '会员参赛名', '足环号码', '错误原因'];
    }

    public function array(): array
    {
        return collect($this->rows)->map(fn (array $row): array => [
            $row['line'],
            $row['data']['sequence'],
            $row['data']['loft_number'],
            $row['data']['participant_name'],
            $row['data']['ring_number'],
            implode('；', $row['errors']),
        ])->values()->all();
    }
}
