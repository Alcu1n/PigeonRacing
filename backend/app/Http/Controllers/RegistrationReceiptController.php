<?php

// [IN]: Authenticated administrator and registration record / 已登录管理员与报名记录
// [OUT]: Permission-checked self-downloading receipt image page and its html2canvas asset / 经权限校验的自动下载报名明细图片页面及其 html2canvas 资源
// [POS]: Admin registration receipt download boundary / 后台报名明细下载边界
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Http\Controllers;

use App\Models\Registration;
use App\Models\User;
use App\Services\RaceCacheService;
use App\Support\AdminPermissions;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RegistrationReceiptController extends Controller
{
    public function show(Registration $registration, RaceCacheService $cache): Response
    {
        $this->authorizeReceiptAccess();

        $registration->load(['race', 'member', 'entries.pigeons', 'progressiveStageEntries.category']);

        return response()->view('receipts.registration-receipt', [
            'payload' => $cache->serializeRegistration($registration),
        ], 200, [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function html2canvas(): BinaryFileResponse
    {
        $this->authorizeReceiptAccess();

        return response()->file(public_path('js/html2canvas.min.js'), [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeReceiptAccess(): void
    {
        $user = auth()->user();
        abort_unless(
            $user instanceof User
            && $user->can(AdminPermissions::name('registrations', 'view')),
            403,
        );
    }
}
