<?php
// [IN]: RegistrationCategoryResource table definition / RegistrationCategoryResource 表格定义
// [OUT]: List page for progressive registration categories / 递进报名类别列表页
// [POS]: Backend admin progressive category list route / 后端后台递进类别列表路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\RegistrationCategoryResource\Pages;

use App\Filament\Resources\RegistrationCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRegistrationCategories extends ListRecords
{
    protected static string $resource = RegistrationCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
