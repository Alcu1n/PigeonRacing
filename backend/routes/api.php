<?php
// [IN]: Member API HTTP requests / 会员 API HTTP 请求
// [OUT]: Member-guard session API responses / member guard 会话 API 响应
// [POS]: Backend API route map / 后端 API 路由地图
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

use App\Http\Controllers\Api\Member\AuthController;
use App\Http\Controllers\Api\Member\BrandingController;
use App\Http\Controllers\Api\Member\ProfileController;
use App\Http\Controllers\Api\Member\RaceController;
use App\Http\Controllers\Api\Member\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->prefix('member')->group(function (): void {
    Route::get('/branding', [BrandingController::class, 'show']);
    Route::post('/login', [AuthController::class, 'login'])->middleware('guest:member');

    Route::middleware('auth:member')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::post('/password', [ProfileController::class, 'updatePassword']);
        Route::get('/races', [RaceController::class, 'index']);
        Route::get('/races/{race}/bootstrap', [RaceController::class, 'bootstrap']);
        Route::post('/races/{race}/registrations', [RegistrationController::class, 'store']);
        Route::get('/registrations/{registration}', [RegistrationController::class, 'show']);
    });
});
