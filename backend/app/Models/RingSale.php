<?php

// [IN]: Buyer snapshot, ring-sale line items, receipts, and payment ledger / 购买人快照、售环明细、收据与收款流水
// [OUT]: Ring-sale aggregate totals and localized financial status / 售环聚合金额与本地化付款状态
// [POS]: Ring-sale aggregate root / 售环聚合根
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class RingSale extends Model
{
    protected $fillable = [
        'sale_no',
        'member_id',
        'buyer_name',
        'loft_number',
        'sale_date',
        'total_quantity',
        'total_amount_cent',
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
            'sale_date' => 'date',
            'total_quantity' => 'integer',
            'total_amount_cent' => 'integer',
            'voided_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RingSaleItem::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RingSalePayment::class)->orderBy('payment_date')->orderBy('id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(RingSaleReceipt::class)->orderBy('sort_order');
    }

    public function allocations()
    {
        return $this->hasManyThrough(
            RingSaleAllocation::class,
            RingSaleItem::class,
            'ring_sale_id',
            'ring_sale_item_id',
        );
    }

    public function scopeWithFinancials(Builder $query): Builder
    {
        return $query->withSum([
            'payments as active_paid_amount_cent' => fn (Builder $payments): Builder => $payments->where('status', 'active'),
        ], 'amount_cent');
    }

    public function scopeContainingRing(Builder $query, string $search): Builder
    {
        $search = trim($search);
        if (preg_match('/\d+$/', $search, $match)) {
            $digitCount = min(12, strlen($match[0]));

            return $query->whereHas('items', function (Builder $items) use ($search, $digitCount): void {
                $items->where(function (Builder $ranges) use ($search, $digitCount): void {
                    for ($width = 1; $width <= $digitCount; $width++) {
                        $number = (int) substr($search, -$width);
                        $prefix = substr($search, 0, -$width);
                        $ranges->orWhere(function (Builder $candidate) use ($prefix, $width, $number): void {
                            $candidate->where('prefix_snapshot', $prefix)
                                ->where('suffix_width', $width)
                                ->where('start_number', '<=', $number)
                                ->where('end_number', '>=', $number);
                        });
                    }
                });
            });
        }

        return $query->whereHas(
            'allocations',
            fn (Builder $allocations): Builder => $allocations->where(
                'canonical_ring_number',
                'like',
                '%'.mb_strtoupper($search, 'UTF-8').'%',
            ),
        );
    }

    public function getPaidAmountCentAttribute(): int
    {
        $selected = $this->getAttributeFromArray('active_paid_amount_cent');
        if ($selected !== null) {
            return (int) $selected;
        }

        if ($this->relationLoaded('payments')) {
            /** @var Collection<int, RingSalePayment> $payments */
            $payments = $this->getRelation('payments');

            return (int) $payments->where('status', 'active')->sum('amount_cent');
        }

        return (int) $this->payments()->where('status', 'active')->sum('amount_cent');
    }

    public function getUnpaidAmountCentAttribute(): int
    {
        return max(0, (int) $this->total_amount_cent - $this->paid_amount_cent);
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        if ($this->status === 'void') {
            return '作废';
        }

        if ($this->paid_amount_cent === 0) {
            return '未付款';
        }

        return $this->unpaid_amount_cent === 0 ? '付清' : '部分付款';
    }

    public function getPaymentStatusColorAttribute(): string
    {
        return match ($this->payment_status_label) {
            '付清' => 'success',
            '部分付款' => 'warning',
            '未付款' => 'danger',
            default => 'gray',
        };
    }
}
