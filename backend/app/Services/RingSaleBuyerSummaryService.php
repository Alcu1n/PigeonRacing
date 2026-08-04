<?php

// [IN]: Exact buyer-name ring-sale records, active financials, categories, payments, and receipts / 精确姓名售环记录、有效财务、类别、收款与收据
// [OUT]: Full buyer summary read model for the Filament detail page / Filament 姓名汇总页完整读模型
// [POS]: Buyer-scoped ring-sale summary query service / 按姓名限定的售环汇总查询服务
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Services;

use App\Models\RingSale;
use Illuminate\Support\Collection;

class RingSaleBuyerSummaryService
{
    /**
     * @return array{
     *     buyer_name: string,
     *     sales: Collection<int, RingSale>,
     *     active_sales: Collection<int, RingSale>,
     *     record_count: int,
     *     active_record_count: int,
     *     void_record_count: int,
     *     total_quantity: int,
     *     category_quantities: Collection<int, array{name: string, quantity: int}>,
     *     total_amount_cent: int,
     *     paid_amount_cent: int,
     *     unpaid_amount_cent: int,
     *     receipt_count: int,
     * }
     */
    public function summarize(string $buyerName): array
    {
        $buyerName = trim($buyerName);
        $sales = RingSale::query()
            ->where('buyer_name', $buyerName)
            ->withFinancials()
            ->with(['items', 'payments.creator', 'receipts', 'creator', 'voider'])
            ->orderByDesc('sale_date')
            ->orderByDesc('id')
            ->get();
        $activeSales = $sales->where('status', 'active')->values();

        $categoryQuantities = $activeSales
            ->flatMap(fn (RingSale $sale): Collection => $sale->items)
            ->groupBy(fn ($item): string => (string) $item->category_name_snapshot)
            ->map(fn (Collection $items, string $name): array => [
                'name' => $name,
                'quantity' => (int) $items->sum('quantity'),
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $totalAmountCent = (int) $activeSales->sum('total_amount_cent');
        $paidAmountCent = (int) $activeSales->sum(
            fn (RingSale $sale): int => $sale->paid_amount_cent,
        );

        return [
            'buyer_name' => $buyerName,
            'sales' => $sales,
            'active_sales' => $activeSales,
            'record_count' => $sales->count(),
            'active_record_count' => $activeSales->count(),
            'void_record_count' => $sales->where('status', 'void')->count(),
            'total_quantity' => (int) $activeSales->sum('total_quantity'),
            'category_quantities' => $categoryQuantities,
            'total_amount_cent' => $totalAmountCent,
            'paid_amount_cent' => $paidAmountCent,
            'unpaid_amount_cent' => max(0, $totalAmountCent - $paidAmountCent),
            'receipt_count' => (int) $sales->sum(fn (RingSale $sale): int => $sale->receipts->count()),
        ];
    }
}
