<?php
// [IN]: RegistrationCategoryResource form definition and category record / RegistrationCategoryResource 表单定义与类别记录
// [OUT]: Edit page for progressive registration categories / 递进报名类别编辑页
// [POS]: Backend admin progressive category edit route / 后端后台递进类别编辑路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\RegistrationCategoryResource\Pages;

use App\Filament\Resources\RegistrationCategoryResource;
use Filament\Resources\Pages\EditRecord;

class EditRegistrationCategory extends EditRecord
{
    protected static string $resource = RegistrationCategoryResource::class;
}
