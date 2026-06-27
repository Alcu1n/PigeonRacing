<?php
// [IN]: MemberResource form definition and member record / MemberResource 表单定义与会员记录
// [OUT]: Filament member edit page with password reset policy / 带密码重置策略的 Filament 会员编辑页面
// [POS]: Backend admin member edit route / 后端后台会员编辑路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('password', $data) && filled($data['password'])) {
            $data['must_change_password'] = true;
        }

        return $data;
    }
}
