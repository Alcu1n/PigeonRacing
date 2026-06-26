<?php
// [IN]: Laravel Excel worksheet arrays / Laravel Excel 工作表数组
// [OUT]: No-op import adapter for raw array extraction / 用于原始数组提取的空导入适配器
// [POS]: Backend spreadsheet parsing adapter / 后端电子表格解析适配器
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

class SpreadsheetArrayImport implements ToArray
{
    public function array(array $array): void
    {
        //
    }
}
