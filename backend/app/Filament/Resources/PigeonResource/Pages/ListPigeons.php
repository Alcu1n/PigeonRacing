<?php
// [IN]: PigeonResource table definition / PigeonResource 表格定义
// [OUT]: Filament pigeon list page / Filament 足环列表页面
// [POS]: Backend admin pigeon index route / 后端后台足环索引路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\PigeonResource\Pages;

use App\Filament\Resources\PigeonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPigeons extends ListRecords
{
    protected static string $resource = PigeonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
