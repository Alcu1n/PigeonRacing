<?php

// [IN]: Validated administrator form state / 已校验的管理员表单状态
// [OUT]: Persisted ordinary administrator with direct permissions / 带直接权限的普通管理员持久化结果
// [POS]: Permission-management create page / 权限管理新增页
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\AdminUserResource\Pages;

use App\Filament\Resources\AdminUserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateAdminUser extends CreateRecord
{
    protected static string $resource = AdminUserResource::class;

    /** @var array<int, string> */
    private array $permissions = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->permissions = array_values($data['permissions'] ?? []);
        unset($data['permissions']);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var User $user */
        $user = $this->getRecord();
        $user->syncRoles(['admin']);
        $user->syncPermissions($this->permissions);
    }
}
