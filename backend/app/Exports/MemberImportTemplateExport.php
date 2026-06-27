<?php
// [IN]: Fixed member import column contract / 固定会员导入列契约
// [OUT]: Downloadable member import template rows / 可下载会员导入模板行
// [POS]: Backend member import template export / 后端会员导入模板导出
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MemberImportTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return ['序号', '棚号', '参赛名', '手机号', '密码'];
    }

    public function array(): array
    {
        return [
            [1, 'A001', '张三鸽舍', '13800000000', 'password'],
        ];
    }
}
