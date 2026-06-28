<?php
// [IN]: Public storage route and fake public disk / 公开存储路由与 fake public 磁盘
// [OUT]: Public file streaming and unsafe path assertions / 公开文件流与不安全路径断言
// [POS]: Backend public storage delivery feature test / 后端公开存储交付功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicStorageControllerTest extends TestCase
{
    public function test_it_streams_public_storage_files_through_laravel(): void
    {
        Storage::fake('public');
        $content = "\x89PNG\r\n\x1A\npublic-logo";
        Storage::disk('public')->put('branding/logo.png', $content);

        $response = $this->get('/storage/branding/logo.png');

        $response->assertOk()
            ->assertHeader('Cache-Control', 'max-age=604800, public');

        $this->assertSame($content, $response->streamedContent());
    }

    public function test_it_rejects_unsafe_public_storage_paths(): void
    {
        Storage::fake('public');

        $this->get('/storage/%2E%2E/.env')
            ->assertNotFound();
    }
}
