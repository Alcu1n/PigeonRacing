<?php
// [IN]: InformationPostResource form definition and record / InformationPostResource 表单定义与记录
// [OUT]: Filament information publishing edit page / Filament 信息发布编辑页面
// [POS]: Backend admin information publishing edit route / 后端后台信息发布编辑路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\InformationPostResource\Pages;

use App\Filament\Resources\InformationPostResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInformationPost extends EditRecord
{
    protected static string $resource = InformationPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
