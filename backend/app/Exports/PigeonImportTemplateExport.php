<?php
// [IN]: Fixed pigeon import column contract / 固定足环导入列契约
// [OUT]: Downloadable pigeon import template rows / 可下载足环导入模板行
// [POS]: Backend pigeon import template export / 后端足环导入模板导出
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PigeonImportTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return ['序号', '会员棚号', '会员参赛名', '足环号码'];
    }

    public function array(): array
    {
        return [
            [1, 'A001', '张三鸽舍', '2025-13-000001'],
        ];
    }
}
