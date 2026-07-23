<?php

// [IN]: Private receipt file metadata / 私有收据文件元数据
// [OUT]: Permission-protected ring-sale receipt reference / 受权限保护的售环收据引用
// [POS]: Ring-sale receipt model / 售环收据模型
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RingSaleReceipt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ring_sale_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'sort_order',
        'uploaded_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return ['size' => 'integer', 'created_at' => 'datetime'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(RingSale::class, 'ring_sale_id');
    }
}
