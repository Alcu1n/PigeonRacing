<?php

// [IN]: Ring-sale administrator permission and clustered resources / 售环管理员权限与模块资源
// [OUT]: One sidebar entry with top sub-navigation / 单一侧栏入口与顶部子导航
// [POS]: Ring-sale Filament cluster / 售环 Filament 模块
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Clusters;

use App\Models\User;
use App\Support\AdminPermissions;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;

class RingSales extends Cluster
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = '售环记录';

    protected static ?string $title = '售环记录';

    protected static ?string $slug = 'ring-sales';

    protected static ?int $navigationSort = 35;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->can(AdminPermissions::name('ring-sales', 'view'));
    }
}
