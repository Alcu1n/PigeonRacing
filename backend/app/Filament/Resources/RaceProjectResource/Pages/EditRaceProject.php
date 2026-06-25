<?php
// [IN]: RaceProjectResource form definition and project record / RaceProjectResource 表单定义与项目记录
// [OUT]: Filament race project edit page / Filament 报名项目编辑页面
// [POS]: Backend admin race project edit route / 后端后台报名项目编辑路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\RaceProjectResource\Pages;

use App\Filament\Resources\RaceProjectResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRaceProject extends EditRecord
{
    protected static string $resource = RaceProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
