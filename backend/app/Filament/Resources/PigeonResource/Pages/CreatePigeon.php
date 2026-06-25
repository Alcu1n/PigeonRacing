<?php
// [IN]: PigeonResource form definition / PigeonResource 表单定义
// [OUT]: Filament pigeon create page / Filament 足环创建页面
// [POS]: Backend admin pigeon create route / 后端后台足环创建路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\PigeonResource\Pages;

use App\Filament\Resources\PigeonResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePigeon extends CreateRecord
{
    protected static string $resource = PigeonResource::class;
}
