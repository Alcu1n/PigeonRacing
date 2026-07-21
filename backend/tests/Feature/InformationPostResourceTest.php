<?php

// [IN]: Filament admin session and InformationPostResource page / Filament 后台会话与 InformationPostResource 页面
// [OUT]: Information publishing create page render assertions / 信息发布创建页渲染断言
// [POS]: Backend information publishing resource feature test / 后端信息发布资源功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Filament\Resources\InformationPostResource;
use App\Models\User;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
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
        $admin->assignRole('super-admin');

        $this->actingAs($admin)
            ->get(InformationPostResource::getUrl('create'))
            ->assertOk()
            ->assertSee('标题')
            ->assertSee('正文');
    }

    public function test_information_editor_uses_single_direct_color_palette_tool(): void
    {
        $method = new ReflectionMethod(InformationPostResource::class, 'textColorPaletteTool');
        $tool = $method->invoke(null);

        $this->assertInstanceOf(RichEditorTool::class, $tool);
        $this->assertSame('textColorPalette', $tool->getName());
        $this->assertStringContainsString('setTextColor({ color })', $tool->getJsHandler());
        $this->assertStringContainsString('data-pigeon-text-color-palette', $tool->getJsHandler());
        $this->assertFalse(method_exists(InformationPostResource::class, 'textColorTool'));
        $this->assertFalse(method_exists(InformationPostResource::class, 'textColorClearTool'));
    }

    public function test_livewire_payload_depth_supports_rich_editor_documents(): void
    {
        $this->assertGreaterThanOrEqual(64, config('livewire.payload.max_nesting_depth'));
        $this->assertSame(1024 * 1024, config('livewire.payload.max_size'));
        $this->assertSame(50, config('livewire.payload.max_calls'));
        $this->assertSame(200, config('livewire.payload.max_components'));
    }
}
