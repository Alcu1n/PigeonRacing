<?php
// [IN]: InformationPostResource table definition / InformationPostResource 表格定义
// [OUT]: Filament information publishing list page / Filament 信息发布列表页面
// [POS]: Backend admin information publishing index route / 后端后台信息发布索引路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\InformationPostResource\Pages;

use App\Filament\Resources\InformationPostResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInformationPosts extends ListRecords
{
    protected static string $resource = InformationPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
