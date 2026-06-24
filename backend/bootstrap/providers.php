<?php
// [IN]: Laravel service provider class list / Laravel 服务提供者类列表
// [OUT]: Registered application providers / 已注册应用服务提供者
// [POS]: Backend provider registry / 后端服务提供者注册表
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
];
