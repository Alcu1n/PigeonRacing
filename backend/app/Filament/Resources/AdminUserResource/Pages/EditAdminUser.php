<?php

// [IN]: Existing ordinary administrator and submitted form state / 既有普通管理员与提交的表单状态
// [OUT]: Updated administrator identity, credentials, and permissions / 更新后的管理员身份、凭据与权限
// [POS]: Permission-management edit page / 权限管理编辑页
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\AdminUserResource\Pages;

use App\Filament\Resources\AdminUserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAdminUser extends EditRecord
{
    protected static string $resource = AdminUserResource::class;

    /** @var array<int, string> */
    private array $permissions = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $user */
        $user = $this->getRecord();
        $data['permissions'] = $user->getDirectPermissions()->pluck('name')->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->permissions = array_values($data['permissions'] ?? []);
        unset($data['permissions']);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var User $user */
        $user = $this->getRecord();
        $user->syncRoles(['admin']);
        $user->syncPermissions($this->permissions);
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->requiresConfirmation()];
    }
}
