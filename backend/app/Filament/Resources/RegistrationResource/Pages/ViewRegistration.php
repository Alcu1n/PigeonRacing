<?php
// [IN]: RegistrationResource form definition and registration record / RegistrationResource 表单定义与报名记录
// [OUT]: Filament registration view page / Filament 报名查看页面
// [POS]: Backend admin registration view route / 后端后台报名查看路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\RegistrationResource\Pages;

use App\Filament\Resources\RegistrationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewRegistration extends ViewRecord
{
    protected static string $resource = RegistrationResource::class;
}
