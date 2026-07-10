<?php

// [IN]: Fixed member credential import column contract / 固定的会员登录凭据导入列契约
// [OUT]: Text-formatted member credential import template / 文本格式的会员登录凭据导入模板
// [POS]: Backend member credential import template export / 后端会员登录凭据导入模板导出
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class MemberCredentialImportTemplateExport implements FromArray, WithColumnFormatting, WithHeadings
{
    public function headings(): array
    {
        return ['会员棚号', '手机号', '密码'];
    }

    public function array(): array
    {
        return [
            ['A001', '13800000000', 'password'],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
        ];
    }
}
