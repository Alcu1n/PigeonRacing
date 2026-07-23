<?php

// [IN]: New ring-number prefix form submission / 新号码前缀表单提交
// [OUT]: Persisted prefix configuration / 已保存的前缀配置
// [POS]: Ring-number prefix create page / 号码前缀新增页
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\RingNumberPrefixResource\Pages;

use App\Filament\Resources\RingNumberPrefixResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRingNumberPrefix extends CreateRecord
{
    protected static string $resource = RingNumberPrefixResource::class;
}
