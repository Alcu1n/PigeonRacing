<?php
// [IN]: MemberResource form definition / MemberResource 表单定义
// [OUT]: Filament member create page / Filament 会员创建页面
// [POS]: Backend admin member create route / 后端后台会员创建路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;
}
