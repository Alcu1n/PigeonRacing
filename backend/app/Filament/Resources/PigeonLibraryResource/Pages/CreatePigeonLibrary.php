<?php

// [IN]: PigeonLibraryResource form definition / PigeonLibraryResource 表单定义
// [OUT]: Filament pigeon library create page / Filament 足环库创建页面
// [POS]: Backend admin pigeon library create route / 后端后台足环库创建路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\PigeonLibraryResource\Pages;

use App\Filament\Resources\PigeonLibraryResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePigeonLibrary extends CreateRecord
{
    protected static string $resource = PigeonLibraryResource::class;
}
