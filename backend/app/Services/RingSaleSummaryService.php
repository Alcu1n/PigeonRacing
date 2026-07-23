<?php

// [IN]: Current filtered ring-sale Eloquent query / 当前筛选后的售环查询
// [OUT]: Active sale count, quantity, receivable, paid, and unpaid totals / 有效售环单数、数量、应收、已收与未收汇总
// [POS]: Ring-sale list summary query service / 售环列表汇总查询服务
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Services;

use App\Models\RingSalePayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RingSaleSummaryService
{
    /** @return array{sales: int, quantity: int, total_amount_cent: int, paid_amount_cent: int, unpaid_amount_cent: int} */
    public function summarize(Builder $filteredQuery): array
    {
        $activeQuery = $filteredQuery->clone()
            ->where('ring_sales.status', 'active')
            ->reorder();

        $financials = DB::query()
            ->fromSub(
                $activeQuery->clone()->select([
                    'ring_sales.id',
                    'ring_sales.total_quantity',
                    'ring_sales.total_amount_cent',
                ]),
                'filtered_ring_sales',
            )
            ->selectRaw('COUNT(*) as sales')
            ->selectRaw('COALESCE(SUM(total_quantity), 0) as quantity')
            ->selectRaw('COALESCE(SUM(total_amount_cent), 0) as total_amount_cent')
            ->first();

        $paid = (int) RingSalePayment::query()
            ->where('status', 'active')
            ->whereIn('ring_sale_id', $activeQuery->clone()->select('ring_sales.id'))
            ->sum('amount_cent');
        $total = (int) ($financials->total_amount_cent ?? 0);

        return [
            'sales' => (int) ($financials->sales ?? 0),
            'quantity' => (int) ($financials->quantity ?? 0),
            'total_amount_cent' => $total,
            'paid_amount_cent' => $paid,
            'unpaid_amount_cent' => max(0, $total - $paid),
        ];
    }
}
