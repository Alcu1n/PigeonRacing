<?php

// [IN]: One ring category and one inclusive sold number range / 一个足环类别与一个已售号码闭区间
// [OUT]: Price and ring-number snapshots for a ring sale / 售环单的价格与号码快照
// [POS]: Ring-sale line-item model / 售环明细模型
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RingSaleItem extends Model
{
    protected $fillable = [
        'ring_sale_id',
        'ring_sale_category_id',
        'ring_number_prefix_id',
        'entry_mode',
        'category_name_snapshot',
        'unit_price_cent',
        'prefix_snapshot',
        'suffix_width',
        'start_number',
        'end_number',
        'start_ring',
        'end_ring',
        'quantity',
        'line_amount_cent',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_cent' => 'integer',
            'suffix_width' => 'integer',
            'start_number' => 'integer',
            'end_number' => 'integer',
            'quantity' => 'integer',
            'line_amount_cent' => 'integer',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(RingSale::class, 'ring_sale_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RingSaleCategory::class, 'ring_sale_category_id');
    }

    public function prefix(): BelongsTo
    {
        return $this->belongsTo(RingNumberPrefix::class, 'ring_number_prefix_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(RingSaleAllocation::class);
    }
}
