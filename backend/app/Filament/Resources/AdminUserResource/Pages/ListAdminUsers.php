<?php

// [IN]: AdminUserResource list route / AdminUserResource 列表路由
// [OUT]: Administrator list with creation entry / 带新增入口的管理员列表
// [POS]: Permission-management list page / 权限管理列表页
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\AdminUserResource\Pages;

use App\Filament\Resources\AdminUserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdminUsers extends ListRecords
{
    protected static string $resource = AdminUserResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
