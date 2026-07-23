<?php

// [IN]: Existing ring-number prefix and edit request / 已有号码前缀与编辑请求
// [OUT]: Updated prefix or enabled state / 已更新的前缀或启用状态
// [POS]: Ring-number prefix edit page / 号码前缀编辑页
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\RingNumberPrefixResource\Pages;

use App\Filament\Resources\RingNumberPrefixResource;
use Filament\Resources\Pages\EditRecord;

class EditRingNumberPrefix extends EditRecord
{
    protected static string $resource = RingNumberPrefixResource::class;
}
