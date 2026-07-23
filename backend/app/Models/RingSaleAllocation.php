<?php

// [IN]: One normalized sold ring number / 一个标准化已售足环号码
// [OUT]: Database-enforced active ring-sale ownership / 数据库强制的有效售环占用
// [POS]: Ring-sale duplicate-prevention model / 售环防重复占用模型
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RingSaleAllocation extends Model
{
    public $timestamps = false;

    protected $fillable = ['ring_sale_item_id', 'canonical_ring_number', 'display_ring_number', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(RingSaleItem::class, 'ring_sale_item_id');
    }
}
