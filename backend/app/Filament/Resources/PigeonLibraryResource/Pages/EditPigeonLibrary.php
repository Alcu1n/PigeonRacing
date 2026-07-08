<?php

// [IN]: PigeonLibraryResource form definition and record / PigeonLibraryResource 表单定义与记录
// [OUT]: Filament pigeon library edit page / Filament 足环库编辑页面
// [POS]: Backend admin pigeon library edit route / 后端后台足环库编辑路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\PigeonLibraryResource\Pages;

use App\Filament\Resources\PigeonLibraryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPigeonLibrary extends EditRecord
{
    protected static string $resource = PigeonLibraryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
