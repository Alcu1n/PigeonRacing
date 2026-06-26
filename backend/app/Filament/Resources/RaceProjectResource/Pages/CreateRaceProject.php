<?php
// [IN]: RaceProjectResource form definition and cache service / RaceProjectResource 表单定义与缓存服务
// [OUT]: Filament race project create page with explicit cache refresh / 带显式缓存刷新的 Filament 报名项目创建页面
// [POS]: Backend admin race project create route / 后端后台报名项目创建路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\RaceProjectResource\Pages;

use App\Filament\Resources\RaceProjectResource;
use App\Services\RaceCacheService;
use Filament\Resources\Pages\CreateRecord;

class CreateRaceProject extends CreateRecord
{
    protected static string $resource = RaceProjectResource::class;

    protected function afterCreate(): void
    {
        app(RaceCacheService::class)->forgetRaceById($this->record->race_id);
    }
}
