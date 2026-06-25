<?php
// [IN]: RaceResource form definition and race record / RaceResource 表单定义与赛事记录
// [OUT]: Filament race edit page / Filament 赛事编辑页面
// [POS]: Backend admin race edit route / 后端后台赛事编辑路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\RaceResource\Pages;

use App\Filament\Resources\RaceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRace extends EditRecord
{
    protected static string $resource = RaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
