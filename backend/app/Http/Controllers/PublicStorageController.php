<?php
// [IN]: Public storage HTTP path and Laravel public disk / 公开存储 HTTP 路径与 Laravel public 磁盘
// [OUT]: Streamed public file response or 404 / 公开文件流式响应或 404
// [POS]: Backend public storage delivery boundary / 后端公开存储交付边界
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicStorageController extends Controller
{
    public function show(string $path): StreamedResponse
    {
        abort_unless($this->isSafePublicPath($path), Response::HTTP_NOT_FOUND);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), Response::HTTP_NOT_FOUND);

        $stream = $disk->readStream($path);
        abort_unless(is_resource($stream), Response::HTTP_NOT_FOUND);

        return response()->stream(
            function () use ($stream): void {
                fpassthru($stream);
                fclose($stream);
            },
            Response::HTTP_OK,
            [
                'Cache-Control' => 'public, max-age=604800',
                'Content-Length' => (string) $disk->size($path),
                'Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream',
            ],
        );
    }

    private function isSafePublicPath(string $path): bool
    {
        return $path !== ''
            && ! str_starts_with($path, '/')
            && ! str_contains($path, '\\')
            && ! str_contains($path, '..');
    }
}
