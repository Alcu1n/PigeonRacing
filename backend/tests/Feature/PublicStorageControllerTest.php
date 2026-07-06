<?php
// [IN]: Public storage route, logo image formats, and fake public disk / 公开存储路由、Logo 图片格式与 fake public 磁盘
// [OUT]: Public file streaming, image MIME, and unsafe path assertions / 公开文件流、图片 MIME 与不安全路径断言
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
            ->assertHeader('Cache-Control', 'max-age=604800, public')
            ->assertHeader('Content-Type', 'image/png');

        $this->assertSame($content, $response->streamedContent());
    }

    public function test_it_streams_webp_logos_with_browser_renderable_mime_type(): void
    {
        Storage::fake('public');
        $content = "RIFF\x12\x00\x00\x00WEBPVP8 public-logo";
        Storage::disk('public')->put('branding/logo.webp', $content);

        $response = $this->get('/storage/branding/logo.webp');

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/webp');

        $this->assertSame($content, $response->streamedContent());
    }

    public function test_it_streams_avif_logos_with_browser_renderable_mime_type(): void
    {
        Storage::fake('public');
        $content = "\x00\x00\x00\x18ftypavifpublic-logo";
        Storage::disk('public')->put('branding/logo.avif', $content);

        $response = $this->get('/storage/branding/logo.avif');

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/avif');

        $this->assertSame($content, $response->streamedContent());
    }

    public function test_it_streams_svg_logos_with_browser_renderable_mime_type(): void
    {
        Storage::fake('public');
        $content = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 40"><text x="0" y="24">Logo</text></svg>';
        Storage::disk('public')->put('branding/logo.svg', $content);

        $response = $this->get('/storage/branding/logo.svg');

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');

        $this->assertSame($content, $response->streamedContent());
    }

    public function test_it_rejects_unsafe_public_storage_paths(): void
    {
        Storage::fake('public');

        $this->get('/storage/%2E%2E/.env')
            ->assertNotFound();
    }
}
