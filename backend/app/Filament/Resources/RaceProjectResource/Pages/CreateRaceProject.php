<?php
// [IN]: RaceProjectResource form definition / RaceProjectResource 表单定义
// [OUT]: Filament race project create page / Filament 报名项目创建页面
// [POS]: Backend admin race project create route / 后端后台报名项目创建路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\RaceProjectResource\Pages;

use App\Filament\Resources\RaceProjectResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRaceProject extends CreateRecord
{
    protected static string $resource = RaceProjectResource::class;
}
