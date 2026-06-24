<?php
// [IN]: HTTP request and Laravel bootstrap / HTTP 请求与 Laravel 启动文件
// [OUT]: HTTP response from Laravel kernel / Laravel 内核 HTTP 响应
// [POS]: Backend public front controller / 后端公开前端控制器
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

require __DIR__.'/../vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
