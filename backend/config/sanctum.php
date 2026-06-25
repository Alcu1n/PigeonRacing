<?php
// [IN]: Sanctum environment values and request host / Sanctum 环境变量与请求 Host
// [OUT]: Stateful SPA authentication settings for localhost and LAN hosts / localhost 与局域网 Host 的有状态 SPA 鉴权设置
// [POS]: Backend Sanctum configuration / 后端 Sanctum 配置
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

use Laravel\Sanctum\Sanctum;

return [
    'stateful' => array_values(array_filter(explode(',', sprintf(
        '%s%s%s',
        env('SANCTUM_STATEFUL_DOMAINS', 'localhost,localhost:5173,127.0.0.1,127.0.0.1:5173,::1'),
        Sanctum::currentApplicationUrlWithPort(),
        Sanctum::currentRequestHost(),
    )))),
    'guard' => ['web', 'member'],
    'expiration' => null,
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),
    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
