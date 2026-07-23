<?php

// [IN]: Sale-date range and optional voided ring sales / 售环日期范围与可选作废售环单
// [OUT]: Three-sheet ring-sale reconciliation workbook / 三工作表售环对账工作簿
// [POS]: Ring-sale Excel export / 售环 Excel 导出
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Exports;

use App\Models\RingSale;
use App\Models\RingSaleItem;
use App\Models\RingSalePayment;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class RingSaleExport implements WithMultipleSheets
{
    /** @var Collection<int, RingSale> */
    private Collection $sales;

    public function __construct(
        string $startDate,
        string $endDate,
        bool $includeVoided = false,
    ) {
        $this->sales = RingSale::query()
            ->withFinancials()
            ->with([
                'creator:id,name',
                'items',
                'payments.creator:id,name',
                'receipts:id,ring_sale_id',
            ])
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->when(! $includeVoided, fn ($query) => $query->where('status', 'active'))
            ->orderBy('sale_date')
            ->orderBy('id')
            ->get();
    }

    public function sheets(): array
    {
        return [
            new RingSaleSummarySheet($this->sales),
            new RingSaleItemsSheet($this->sales),
            new RingSalePaymentsSheet($this->sales),
        ];
    }
}

abstract class RingSaleTextSafeSheet extends DefaultValueBinder implements FromCollection, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithTitle
{
    /** @param Collection<int, RingSale> $sales */
    public function __construct(protected readonly Collection $sales) {}

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    protected function yuan(int $amountCent): float
    {
        return $amountCent / 100;
    }
}

class RingSaleSummarySheet extends RingSaleTextSafeSheet
{
    public function title(): string
    {
        return '售环单汇总';
    }

    public function headings(): array
    {
        return [
            '序号',
            '售环单号',
            '状态',
            '售环日期',
            '姓名',
            '棚号',
            '足环总数',
            '总金额',
            '已付金额',
            '未付金额',
            '备注',
            '收据照片数',
            '创建人',
            '创建时间',
        ];
    }

    public function collection(): Collection
    {
        return $this->sales->values()->map(fn (RingSale $sale, int $index): array => [
            $index + 1,
            $sale->sale_no,
            $sale->payment_status_label,
            $sale->sale_date->toDateString(),
            $sale->buyer_name,
            $sale->loft_number ?? '',
            $sale->total_quantity,
            $this->yuan($sale->total_amount_cent),
            $this->yuan($sale->paid_amount_cent),
            $this->yuan($sale->unpaid_amount_cent),
            $sale->remark ?? '',
            $sale->receipts->count(),
            $sale->creator?->name ?? '',
            $sale->created_at?->format('Y-m-d H:i:s') ?? '',
        ]);
    }
}

class RingSaleItemsSheet extends RingSaleTextSafeSheet
{
    public function title(): string
    {
        return '号码段明细';
    }

    public function headings(): array
    {
        return [
            '售环单号',
            '状态',
            '售环日期',
            '姓名',
            '棚号',
            '类别',
            '单价',
            '起始号',
            '结束号',
            '数量',
            '明细金额',
        ];
    }

    public function collection(): Collection
    {
        return $this->sales->flatMap(fn (RingSale $sale): Collection => $sale->items
            ->map(fn (RingSaleItem $item): array => [
                $sale->sale_no,
                $sale->payment_status_label,
                $sale->sale_date->toDateString(),
                $sale->buyer_name,
                $sale->loft_number ?? '',
                $item->category_name_snapshot,
                $this->yuan($item->unit_price_cent),
                $item->start_ring,
                $item->end_ring,
                $item->quantity,
                $this->yuan($item->line_amount_cent),
            ]))->values();
    }
}

class RingSalePaymentsSheet extends RingSaleTextSafeSheet
{
    public function title(): string
    {
        return '收款明细';
    }

    public function headings(): array
    {
        return [
            '售环单号',
            '售环单状态',
            '姓名',
            '收款日期',
            '收款金额',
            '流水状态',
            '备注',
            '登记人',
            '登记时间',
        ];
    }

    public function collection(): Collection
    {
        return $this->sales->flatMap(fn (RingSale $sale): Collection => $sale->payments
            ->map(fn (RingSalePayment $payment): array => [
                $sale->sale_no,
                $sale->payment_status_label,
                $sale->buyer_name,
                $payment->payment_date->toDateString(),
                $this->yuan($payment->amount_cent),
                $payment->status === 'active' ? '有效' : '作废',
                $payment->remark ?? '',
                $payment->creator?->name ?? '',
                $payment->created_at?->format('Y-m-d H:i:s') ?? '',
            ]))->values();
    }
}
