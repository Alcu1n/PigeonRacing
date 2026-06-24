<?php
// [IN]: Artisan scheduler and command registry / Artisan 调度器与命令注册
// [OUT]: Console route definitions / 控制台路由定义
// [POS]: Backend console route boundary / 后端控制台路由边界
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

use Illuminate\Support\Facades\Schedule;

Schedule::command('queue:work --stop-when-empty')->everyMinute()->withoutOverlapping();
