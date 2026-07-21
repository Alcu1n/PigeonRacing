<?php

// [IN]: Current administrator and resource module identifier / 当前管理员与资源模块标识
// [OUT]: Shared Filament CRUD authorization checks / 共享的 Filament CRUD 授权检查
// [POS]: Resource-level permission adapter / 资源级权限适配器
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Concerns;

use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait HasModulePermissions
{
    public static function getEloquentQuery(): Builder
    {
        abort_unless(static::hasModulePermission('view'), 403);

        return parent::getEloquentQuery();
    }

    public static function canViewAny(): bool
    {
        return static::hasModulePermission('view');
    }

    public static function canCreate(): bool
    {
        return static::hasModulePermission('create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::hasModulePermission('update');
    }

    public static function canDelete(Model $record): bool
    {
        return static::hasModulePermission('delete');
    }

    public static function canDeleteAny(): bool
    {
        return static::hasModulePermission('delete');
    }

    public static function hasModulePermission(string $action): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->can(AdminPermissions::name(static::$permissionModule, 'view'))
            && $user->can(AdminPermissions::name(static::$permissionModule, $action));
    }
}
