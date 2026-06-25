<?php
// [IN]: RaceResource table definition / RaceResource 表格定义
// [OUT]: Filament race list page / Filament 赛事列表页面
// [POS]: Backend admin race index route / 后端后台赛事索引路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\RaceResource\Pages;

use App\Filament\Resources\RaceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRaces extends ListRecords
{
    protected static string $resource = RaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
