<?php

// [IN]: One payment amount, date, status, and operator / 一笔收款金额、日期、状态与操作人
// [OUT]: Auditable ring-sale payment ledger entry / 可审计的售环收款流水
// [POS]: Ring-sale payment model / 售环收款模型
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RingSalePayment extends Model
{
    protected $fillable = [
        'ring_sale_id',
        'payment_date',
        'amount_cent',
        'status',
        'remark',
        'void_reason',
        'voided_at',
        'voided_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount_cent' => 'integer',
            'voided_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(RingSale::class, 'ring_sale_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
