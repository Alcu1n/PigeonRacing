<?php

// [IN]: Runtime locale endpoint, shared app_locale cookie, and Accept-Language header / 运行时语言接口、共享 app_locale Cookie 与 Accept-Language 标头
// [OUT]: Public locale JSON contract and manual-cookie precedence assertions / 公开语言 JSON 契约与手动 Cookie 优先级断言
// [POS]: Runtime locale feature test / 运行时语言功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use Tests\TestCase;

class RuntimeConfigLocaleTest extends TestCase
{
    public function test_runtime_config_uses_shared_manual_cookie(): void
    {
        $this->withCredentials()
            ->withUnencryptedCookie('app_locale', 'zh-TW')
            ->getJson('/api/public/runtime-config')
            ->assertOk()
            ->assertExactJson([
                'locale' => 'zh-TW',
                'source' => 'manual',
            ]);
    }

    public function test_runtime_config_ignores_accept_language_when_ip_geoip_is_unavailable(): void
    {
        $this->withHeader('Accept-Language', 'zh-TW')
            ->getJson('/api/public/runtime-config')
            ->assertOk()
            ->assertJson([
                'locale' => 'zh-CN',
                'source' => 'fallback',
            ]);
    }
}
