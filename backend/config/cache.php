<?php
// [IN]: Cache environment variables / 缓存环境变量
// [OUT]: Laravel cache store configuration / Laravel 缓存存储配置
// [POS]: Backend cache configuration / 后端缓存配置
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

return [
    'default' => env('CACHE_STORE', 'database'),
    'stores' => [
        'database' => [
            'driver' => 'database',
            'table' => 'cache',
            'lock_table' => 'cache_locks',
        ],
        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'lock_connection' => 'default',
        ],
        'array' => ['driver' => 'array'],
    ],
    'prefix' => env('CACHE_PREFIX', 'pigeon_registration_cache'),
];
