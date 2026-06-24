<?php
// [IN]: Application environment variables / 应用环境变量
// [OUT]: Laravel app runtime configuration / Laravel 应用运行配置
// [POS]: Backend application configuration / 后端应用配置
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

return [
    'name' => env('APP_NAME', '赛鸽赛事报名系统'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => 'Asia/Shanghai',
    'locale' => 'zh_CN',
    'fallback_locale' => 'en',
    'faker_locale' => 'zh_CN',
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
    'maintenance' => ['driver' => 'file'],
];
