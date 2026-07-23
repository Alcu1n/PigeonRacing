<?php

// [IN]: Existing ring-sale category and edit request / 已有足环类别与编辑请求
// [OUT]: Updated category or enabled state / 已更新的类别或启用状态
// [POS]: Ring-sale category edit page / 足环类别编辑页
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\RingSaleCategoryResource\Pages;

use App\Filament\Resources\RingSaleCategoryResource;
use Filament\Resources\Pages\EditRecord;

class EditRingSaleCategory extends EditRecord
{
    protected static string $resource = RingSaleCategoryResource::class;
}
