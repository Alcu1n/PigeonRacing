<?php
// [IN]: Livewire upload requests from Filament admin pages / Filament 后台页面的 Livewire 上传请求
// [OUT]: Temporary upload rules for large Excel imports / 大 Excel 导入的临时上传规则
// [POS]: Backend Livewire runtime configuration override / 后端 Livewire 运行时配置覆盖
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

return [
    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'),
        'rules' => ['required', 'file', 'max:51200'],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
            'xlsx', 'xls',
        ],
        'max_upload_time' => 10,
        'cleanup' => true,
    ],
];
