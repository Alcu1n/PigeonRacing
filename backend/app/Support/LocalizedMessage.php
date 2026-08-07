<?php

// [IN]: Translation key and optional replacement values / 翻译键与可选替换值
// [OUT]: Localized message or standalone-safe fallback / 已本地化文案或可脱离容器运行的回退文案
// [POS]: Shared boundary for domain messages used by Laravel and pure unit tests / 同时供 Laravel 与纯单元测试使用的领域文案边界
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Support;

use Throwable;

final class LocalizedMessage
{
    public static function get(string $key, array $replace = []): string
    {
        try {
            if (function_exists('app') && app()->bound('translator')) {
                return (string) __($key, $replace);
            }
        } catch (Throwable) {
            // Domain validation also runs in standalone unit tests without a Laravel container.
        }

        $fallback = [];
        foreach ($replace as $name => $value) {
            $fallback[':'.$name] = (string) $value;
        }

        return strtr($key, $fallback);
    }
}
