<?php

// [IN]: Sanitized failed member credential import preview rows / 已脱敏的会员登录凭据导入失败行
// [OUT]: Password-free credential import error workbook / 不含密码的登录凭据导入错误表格
// [POS]: Backend member credential import error export / 后端会员登录凭据导入错误导出
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class MemberCredentialImportErrorExport extends StringValueBinder implements FromArray, WithCustomValueBinder, WithHeadings
{
    public function __construct(private readonly array $rows) {}

    public function headings(): array
    {
        return ['行号', '会员棚号', '手机号', '错误原因'];
    }

    public function array(): array
    {
        return collect($this->rows)->map(fn (array $row): array => [
            $row['line'],
            $row['data']['loft_number'],
            $row['data']['phone'],
            implode('；', $row['errors']),
        ])->values()->all();
    }
}
