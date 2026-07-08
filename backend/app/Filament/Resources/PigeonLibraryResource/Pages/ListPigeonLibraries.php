<?php

// [IN]: PigeonLibraryResource table definition / PigeonLibraryResource 表格定义
// [OUT]: Filament pigeon library list page / Filament 足环库列表页面
// [POS]: Backend admin pigeon library index route / 后端后台足环库索引路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\PigeonLibraryResource\Pages;

use App\Filament\Resources\PigeonLibraryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPigeonLibraries extends ListRecords
{
    protected static string $resource = PigeonLibraryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
