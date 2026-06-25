<?php
// [IN]: RaceProjectResource table definition / RaceProjectResource 表格定义
// [OUT]: Filament race project list page / Filament 报名项目列表页面
// [POS]: Backend admin race project index route / 后端后台报名项目索引路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\RaceProjectResource\Pages;

use App\Filament\Resources\RaceProjectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRaceProjects extends ListRecords
{
    protected static string $resource = RaceProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
