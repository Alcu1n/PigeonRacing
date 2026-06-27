<?php
// [IN]: Public branding API and app setting rows / 公开品牌 API 与应用设置行
// [OUT]: Relative login logo URL assertions / 相对登录 Logo 地址断言
// [POS]: Backend member branding API feature test / 后端会员品牌 API 功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberBrandingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_null_logo_when_brand_logo_is_not_uploaded(): void
    {
        $this->getJson('/api/member/branding')
            ->assertOk()
            ->assertJsonPath('logo_url', null);
    }

    public function test_it_returns_relative_public_storage_logo_url(): void
    {
        AppSetting::putValue(AppSetting::BRAND_LOGO_PATH, 'branding/logo.png');

        $this->getJson('/api/member/branding')
            ->assertOk()
            ->assertJsonPath('logo_url', '/storage/branding/logo.png');
    }
}
