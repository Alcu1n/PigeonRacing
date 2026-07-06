<?php
// [IN]: Livewire upload and form payload requests from Filament admin pages / Filament 后台页面的 Livewire 上传与表单请求
// [OUT]: Upload rules and payload guards for Excel imports and rich editor saves / Excel 导入与富文本保存的上传规则和载荷保护
// [POS]: Backend Livewire runtime configuration override / 后端 Livewire 运行时配置覆盖
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

return [
    'payload' => [
        'max_size' => (int) env('LIVEWIRE_PAYLOAD_MAX_SIZE', 1024 * 1024),
        'max_nesting_depth' => (int) env('LIVEWIRE_PAYLOAD_MAX_NESTING_DEPTH', 64),
        'max_calls' => (int) env('LIVEWIRE_PAYLOAD_MAX_CALLS', 50),
        'max_components' => (int) env('LIVEWIRE_PAYLOAD_MAX_COMPONENTS', 200),
    ],

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
