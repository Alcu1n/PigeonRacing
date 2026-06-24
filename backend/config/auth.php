<?php
// [IN]: Laravel auth guard and provider settings / Laravel 鉴权守卫与提供者设置
// [OUT]: Admin web and member API authentication config / 后台 Web 与会员 API 鉴权配置
// [POS]: Backend authentication configuration / 后端鉴权配置
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

return [
    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'member' => [
            'driver' => 'session',
            'provider' => 'members',
        ],
    ],
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
        'members' => [
            'driver' => 'eloquent',
            'model' => App\Models\Member::class,
        ],
    ],
    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],
    'password_timeout' => 10800,
];
