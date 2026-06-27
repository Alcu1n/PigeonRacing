<?php
// [IN]: Filament admin session and brand settings page / Filament 后台会话与品牌设置页
// [OUT]: Brand settings page render assertions / 品牌设置页渲染断言
// [POS]: Backend admin brand settings page feature test / 后端后台品牌设置页功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_brand_settings_page(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $this->actingAs($admin)
            ->get('/admin/brand-settings')
            ->assertOk()
            ->assertSee('品牌 Logo');
    }
}
