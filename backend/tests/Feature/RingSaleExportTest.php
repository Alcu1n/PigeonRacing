<?php

// [IN]: Date-scoped ring sales, line items, and payment ledger / 按日期筛选的售环单、明细与收款流水
// [OUT]: Three-sheet Excel workbook with stable text values / 保持文本稳定的三工作表 Excel
// [POS]: Ring-sale export feature tests / 售环导出功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Exports\RingSaleExport;
use App\Models\RingSaleCategory;
use App\Models\User;
use App\Services\RingSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class RingSaleExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exports_summary_segments_and_all_payments_for_sales_in_the_date_range(): void
    {
        $admin = User::query()->create([
            'name' => '导出管理员',
            'email' => 'ring-export@example.com',
            'password' => 'password',
        ]);
        $category = RingSaleCategory::query()->create([
            'name' => '普通环',
            'unit_price_cent' => 200,
            'is_enabled' => true,
        ]);
        $service = app(RingSaleService::class);
        $sale = $service->create([
            'buyer_name' => '=危险姓名',
            'loft_number' => '0008',
            'sale_date' => '2026-07-10',
            'items' => [[
                'category_id' => $category->id,
                'entry_mode' => 'full',
                'start_ring' => '2026-13-0550001',
                'end_ring' => '2026-13-0550002',
            ]],
            'initial_paid_amount_cent' => 200,
            'initial_payment_date' => '2026-07-10',
            'receipt_paths' => [],
        ], $admin);
        $service->addPayment($sale, [
            'payment_date' => '2026-07-20',
            'amount_cent' => 200,
            'remark' => '跨月补款',
        ], $admin);

        $path = tempnam(sys_get_temp_dir(), 'ring-sale-export-').'.xlsx';
        file_put_contents($path, Excel::raw(
            new RingSaleExport('2026-07-01', '2026-07-15', false),
            ExcelFormat::XLSX,
        ));

        $workbook = IOFactory::load($path);
        $this->assertSame(['售环单汇总', '号码段明细', '收款明细'], $workbook->getSheetNames());

        $summary = $workbook->getSheetByName('售环单汇总');
        $this->assertSame('售环单号', $summary->getCell('B1')->getValue());
        $this->assertSame($sale->sale_no, $summary->getCell('B2')->getValue());
        $this->assertSame('=危险姓名', $summary->getCell('E2')->getValue());
        $this->assertSame('0008', $summary->getCell('F2')->getValue());
        $this->assertEquals(4.0, $summary->getCell('I2')->getValue());

        $segments = $workbook->getSheetByName('号码段明细');
        $this->assertSame('2026-13-0550001', $segments->getCell('H2')->getValue());
        $this->assertSame('2026-13-0550002', $segments->getCell('I2')->getValue());

        $payments = $workbook->getSheetByName('收款明细');
        $this->assertSame('2026-07-20', $payments->getCell('D3')->getValue());
        $this->assertSame('跨月补款', $payments->getCell('G3')->getValue());
    }

    public function test_voided_sales_are_excluded_by_default_and_marked_when_included(): void
    {
        $admin = User::query()->create([
            'name' => '导出管理员',
            'email' => 'ring-void-export@example.com',
            'password' => 'password',
        ]);
        $category = RingSaleCategory::query()->create([
            'name' => '普通环',
            'unit_price_cent' => 200,
            'is_enabled' => true,
        ]);
        $service = app(RingSaleService::class);
        $sale = $service->create([
            'buyer_name' => '已作废购买人',
            'sale_date' => '2026-07-10',
            'items' => [[
                'category_id' => $category->id,
                'entry_mode' => 'full',
                'start_ring' => '2026-13-0660001',
                'end_ring' => '2026-13-0660001',
            ]],
            'initial_paid_amount_cent' => 0,
            'receipt_paths' => [],
        ], $admin);
        $service->voidSale($sale, '测试作废', $admin);

        $excludedPath = tempnam(sys_get_temp_dir(), 'ring-sale-excluded-').'.xlsx';
        file_put_contents($excludedPath, Excel::raw(
            new RingSaleExport('2026-07-01', '2026-07-31', false),
            ExcelFormat::XLSX,
        ));
        $includedPath = tempnam(sys_get_temp_dir(), 'ring-sale-included-').'.xlsx';
        file_put_contents($includedPath, Excel::raw(
            new RingSaleExport('2026-07-01', '2026-07-31', true),
            ExcelFormat::XLSX,
        ));

        $this->assertSame(1, IOFactory::load($excludedPath)->getSheetByName('售环单汇总')->getHighestDataRow());
        $included = IOFactory::load($includedPath)->getSheetByName('售环单汇总');
        $this->assertSame(2, $included->getHighestDataRow());
        $this->assertSame('作废', $included->getCell('C2')->getValue());
    }
}
