<?php
// [IN]: Member API HTTP requests / 会员 API HTTP 请求
// [OUT]: Authenticated member API responses / 已鉴权会员 API 响应
// [POS]: Backend API route map / 后端 API 路由地图
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

use App\Http\Controllers\Api\Member\AuthController;
use App\Http\Controllers\Api\Member\RaceController;
use App\Http\Controllers\Api\Member\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::prefix('member')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->middleware('guest:member');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/races', [RaceController::class, 'index']);
        Route::get('/races/{race}/bootstrap', [RaceController::class, 'bootstrap']);
        Route::post('/races/{race}/registrations', [RegistrationController::class, 'store']);
        Route::get('/registrations/{registration}', [RegistrationController::class, 'show']);
    });
});
