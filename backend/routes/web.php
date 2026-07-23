<?php

// [IN]: Browser requests outside API routes and public storage paths / API 之外的浏览器请求与公开存储路径
// [OUT]: Web route responses, public storage files, and SPA fallback / Web 路由响应、公开存储文件与 SPA 回退
// [POS]: Backend web route boundary / 后端 Web 路由边界
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

use App\Http\Controllers\PublicStorageController;
use App\Http\Controllers\RingSaleReceiptController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::get('/storage/{path}', [PublicStorageController::class, 'show'])
    ->where('path', '.*')
    ->name('storage.public');

Route::get('/admin/ring-sale-receipts/{receipt}', [RingSaleReceiptController::class, 'show'])
    ->middleware('auth')
    ->name('admin.ring-sale-receipts.show');
