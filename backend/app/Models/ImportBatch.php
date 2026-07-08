<?php

// [IN]: Excel import job result rows / Excel 导入任务结果行
// [OUT]: Import counters and error report path / 导入计数与错误报告路径
// [POS]: Backend Excel import batch model / 后端 Excel 导入批次模型
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    protected $fillable = [
        'pigeon_library_id',
        'file_name',
        'total_rows',
        'success_rows',
        'failed_rows',
        'duplicate_rows',
        'uploaded_by',
        'status',
        'error_report_path',
    ];
}
