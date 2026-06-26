<?php
// [IN]: RaceProjectResource form definition, project record, and cache service / RaceProjectResource 表单定义、项目记录与缓存服务
// [OUT]: Filament race project edit page with explicit cache refresh / 带显式缓存刷新的 Filament 报名项目编辑页面
// [POS]: Backend admin race project edit route / 后端后台报名项目编辑路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\RaceProjectResource\Pages;

use App\Filament\Resources\RaceProjectResource;
use App\Models\RaceProject;
use App\Services\RaceCacheService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRaceProject extends EditRecord
{
    protected static string $resource = RaceProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->after(fn (RaceProject $record) => app(RaceCacheService::class)->forgetRaceById($record->race_id)),
        ];
    }

    protected function afterSave(): void
    {
        app(RaceCacheService::class)->forgetRaceById($this->record->race_id);
    }
}
