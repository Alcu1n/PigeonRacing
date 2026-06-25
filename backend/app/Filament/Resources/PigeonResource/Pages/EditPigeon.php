<?php
// [IN]: PigeonResource form definition and pigeon record / PigeonResource 表单定义与足环记录
// [OUT]: Filament pigeon edit page / Filament 足环编辑页面
// [POS]: Backend admin pigeon edit route / 后端后台足环编辑路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\PigeonResource\Pages;

use App\Filament\Resources\PigeonResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPigeon extends EditRecord
{
    protected static string $resource = PigeonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
