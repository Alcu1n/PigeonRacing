<?php

// [IN]: Ring-number prefix collection / 号码前缀集合
// [OUT]: Prefix list and create action / 前缀列表与新增动作
// [POS]: Ring-number prefix list page / 号码前缀列表页
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\RingNumberPrefixResource\Pages;

use App\Filament\Resources\RingNumberPrefixResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRingNumberPrefixes extends ListRecords
{
    protected static string $resource = RingNumberPrefixResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('新增前缀')];
    }
}
