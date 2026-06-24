<?php
// [IN]: Browser requests outside API routes / API 之外的浏览器请求
// [OUT]: Web route responses and SPA fallback / Web 路由响应与 SPA 回退
// [POS]: Backend web route boundary / 后端 Web 路由边界
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
