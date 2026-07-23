<?php

// [IN]: Filament admin session, brand settings page, PWA shell, and topbar contact hook / Filament 后台会话、品牌设置页、PWA 外壳与顶部栏联系信息钩子
// [OUT]: Brand settings, PWA shell, and admin topbar render assertions / 品牌设置、PWA 外壳与后台顶部栏渲染断言
// [POS]: Backend admin shell feature test / 后端后台外壳功能测试
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
        $admin->assignRole('super-admin');

        $this->actingAs($admin)
            ->get('/admin/brand-settings')
            ->assertOk()
            ->assertSee('品牌 Logo')
            ->assertSee('WebP')
            ->assertSee('AVIF')
            ->assertSee('SVG')
            ->assertSee('联系电话：')
            ->assertSee('18650024626')
            ->assertSee('定制开发 微信：')
            ->assertSee('lemonrere')
            ->assertSee('/admin/manifest.webmanifest', false)
            ->assertSee('/admin/service-worker.js', false)
            ->assertSee('scope: \'/admin/\'', false);
    }
}
