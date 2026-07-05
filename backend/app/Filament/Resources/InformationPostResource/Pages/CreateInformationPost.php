<?php
// [IN]: InformationPostResource form definition / InformationPostResource 表单定义
// [OUT]: Filament information publishing create page / Filament 信息发布创建页面
// [POS]: Backend admin information publishing create route / 后端后台信息发布创建路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\InformationPostResource\Pages;

use App\Filament\Resources\InformationPostResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInformationPost extends CreateRecord
{
    protected static string $resource = InformationPostResource::class;
}
