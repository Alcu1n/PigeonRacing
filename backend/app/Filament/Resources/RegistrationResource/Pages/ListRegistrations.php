<?php
// [IN]: RegistrationResource table definition / RegistrationResource 表格定义
// [OUT]: Filament registration list page / Filament 报名列表页面
// [POS]: Backend admin registration index route / 后端后台报名索引路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\RegistrationResource\Pages;

use App\Filament\Resources\RegistrationResource;
use Filament\Resources\Pages\ListRecords;

class ListRegistrations extends ListRecords
{
    protected static string $resource = RegistrationResource::class;
}
