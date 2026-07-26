<?php

// [IN]: Filament page scaffolding / Filament 页面脚手架
// [OUT]: Static usage tutorial page for all administrators / 面向所有管理员的静态使用教程页面
// [POS]: Backend admin usage tutorial page / 后端后台使用教程页
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Pages;

use Filament\Pages\Page;

class UsageTutorial extends Page
{
    protected static ?string $title = '使用教程';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = '使用教程';

    protected static string|\UnitEnum|null $navigationGroup = '系统设置';

    protected static ?int $navigationSort = 91;

    protected string $view = 'filament.pages.usage-tutorial';
}
