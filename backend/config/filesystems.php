<?php
// [IN]: Laravel storage path helpers / Laravel 存储路径助手
// [OUT]: Local disk, readable public file permissions, and same-origin public file URLs / 本地磁盘、可读公开文件权限与同源公开文件 URL
// [POS]: Backend filesystem configuration / 后端文件系统配置
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('PUBLIC_STORAGE_URL', '/storage'),
            'visibility' => 'public',
            'permissions' => [
                'file' => [
                    'public' => 0644,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0755,
                    'private' => 0700,
                ],
            ],
            'throw' => false,
        ],
    ],
];
