<?php
// [IN]: Filament admin session and InformationPostResource page / Filament 后台会话与 InformationPostResource 页面
// [OUT]: Information publishing create page render assertions / 信息发布创建页渲染断言
// [POS]: Backend information publishing resource feature test / 后端信息发布资源功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Filament\Resources\InformationPostResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InformationPostResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_information_post_create_page(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $this->actingAs($admin)
            ->get(InformationPostResource::getUrl('create'))
            ->assertOk()
            ->assertSee('标题')
            ->assertSee('正文');
    }
}
