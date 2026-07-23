<?php

// [IN]: Ring-sale category collection / 足环类别集合
// [OUT]: Category list and create action / 类别列表与新增动作
// [POS]: Ring-sale category list page / 足环类别列表页
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\RingSaleCategoryResource\Pages;

use App\Filament\Resources\RingSaleCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRingSaleCategories extends ListRecords
{
    protected static string $resource = RingSaleCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('新增类别')];
    }
}
