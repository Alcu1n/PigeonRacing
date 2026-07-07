<?php
// [IN]: RegistrationCategoryResource form definition / RegistrationCategoryResource 表单定义
// [OUT]: Create page for progressive registration categories / 递进报名类别创建页
// [POS]: Backend admin progressive category create route / 后端后台递进类别创建路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\RegistrationCategoryResource\Pages;

use App\Filament\Resources\RegistrationCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRegistrationCategory extends CreateRecord
{
    protected static string $resource = RegistrationCategoryResource::class;
}
