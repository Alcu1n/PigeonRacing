<?php
// [IN]: Member and public information API HTTP requests / 会员与公开信息发布 API HTTP 请求
// [OUT]: No-store member-guard responses and published information JSON / no-store member guard 响应与已发布信息 JSON
// [POS]: Backend API route map / 后端 API 路由地图
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

use App\Http\Controllers\Api\Member\AuthController;
use App\Http\Controllers\Api\Member\BrandingController;
use App\Http\Controllers\Api\Member\ProfileController;
use App\Http\Controllers\Api\Member\RaceController;
use App\Http\Controllers\Api\Member\RegistrationController;
use App\Http\Controllers\Api\Public\InformationController;
use App\Http\Middleware\NoStoreMemberApiResponse;
use Illuminate\Support\Facades\Route;

Route::prefix('public')->group(function (): void {
    Route::get('/information', [InformationController::class, 'index']);
    Route::get('/information/{slug}', [InformationController::class, 'show']);
});

Route::middleware(['web', NoStoreMemberApiResponse::class])->prefix('member')->group(function (): void {
    Route::get('/branding', [BrandingController::class, 'show']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:member')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::post('/password', [ProfileController::class, 'updatePassword']);
        Route::get('/races', [RaceController::class, 'index']);
        Route::get('/races/{race}/bootstrap', [RaceController::class, 'bootstrap']);
        Route::get('/registrations', [RegistrationController::class, 'index']);
        Route::post('/races/{race}/registrations', [RegistrationController::class, 'store']);
        Route::get('/registrations/{registration}', [RegistrationController::class, 'show']);
    });
});
