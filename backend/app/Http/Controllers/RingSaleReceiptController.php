<?php

// [IN]: Authenticated administrator and private receipt record / 已登录管理员与私有收据记录
// [OUT]: Permission-checked inline image response / 经权限校验的图片内联响应
// [POS]: Ring-sale private receipt delivery boundary / 售环私有收据交付边界
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Http\Controllers;

use App\Models\RingSaleReceipt;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RingSaleReceiptController extends Controller
{
    public function show(RingSaleReceipt $receipt): StreamedResponse|Response
    {
        $user = auth()->user();
        abort_unless(
            $user instanceof User
            && $user->can(AdminPermissions::name('ring-sales', 'view')),
            403,
        );

        $disk = Storage::disk($receipt->disk);
        abort_unless($disk->exists($receipt->path), 404);

        return $disk->response(
            $receipt->path,
            $receipt->original_name,
            [
                'Content-Type' => $receipt->mime_type ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.$receipt->original_name.'"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }
}
