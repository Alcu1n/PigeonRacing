<?php

// [IN]: New ring-sale category form submission / 新足环类别表单提交
// [OUT]: Persisted category configuration / 已保存的类别配置
// [POS]: Ring-sale category create page / 足环类别新增页
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\RingSaleCategoryResource\Pages;

use App\Filament\Resources\RingSaleCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRingSaleCategory extends CreateRecord
{
    protected static string $resource = RingSaleCategoryResource::class;
}
