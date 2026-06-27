<?php
// [IN]: Failed member import preview rows / 失败会员导入预览行
// [OUT]: Downloadable member import error report / 可下载会员导入错误报告
// [POS]: Backend member import error export / 后端会员导入错误导出
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MemberImportErrorExport implements FromArray, WithHeadings
{
    public function __construct(private readonly array $rows) {}

    public function headings(): array
    {
        return ['行号', '序号', '棚号', '参赛名', '手机号', '密码', '错误原因'];
    }

    public function array(): array
    {
        return collect($this->rows)->map(fn (array $row): array => [
            $row['line'],
            $row['data']['sequence'],
            $row['data']['loft_number'],
            $row['data']['participant_name'],
            $row['data']['phone'],
            $row['data']['password'] === '' ? '' : '已填写',
            implode('；', $row['errors']),
        ])->values()->all();
    }
}
