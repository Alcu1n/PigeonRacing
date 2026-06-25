<?php
// [IN]: Laravel application bootstrap / Laravel 应用启动器
// [OUT]: Base test case with application instance / 带应用实例的测试基类
// [POS]: Backend Laravel feature-test base / 后端 Laravel 功能测试基类
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
