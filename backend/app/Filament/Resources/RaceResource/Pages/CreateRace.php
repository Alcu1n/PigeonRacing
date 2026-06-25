<?php
// [IN]: RaceResource form definition / RaceResource 表单定义
// [OUT]: Filament race create page / Filament 赛事创建页面
// [POS]: Backend admin race create route / 后端后台赛事创建路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\RaceResource\Pages;

use App\Filament\Resources\RaceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRace extends CreateRecord
{
    protected static string $resource = RaceResource::class;
}
