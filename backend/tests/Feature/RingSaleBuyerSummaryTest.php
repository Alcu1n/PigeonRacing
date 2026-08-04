<?php

// [IN]: Buyer-scoped ring-sale records, payments, receipts, and Filament access / 按姓名售环记录、收款、收据与 Filament 鉴权
// [OUT]: Summary aggregates, FIFO buyer payments, permissions, and shareable route behavior / 汇总聚合、姓名总收款、权限与可分享路由行为
// [POS]: Ring-sale buyer summary feature tests / 售环姓名汇总功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Filament\Resources\RingSaleResource;
use App\Filament\Resources\RingSaleResource\Pages\BuyerRingSaleSummary;
use App\Models\AdminLog;
use App\Models\RingSale;
use App\Models\RingSaleCategory;
use App\Models\RingSaleReceipt;
use App\Models\User;
use App\Services\RingSaleBuyerSummaryService;
use App\Services\RingSaleService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\ViewException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RingSaleBuyerSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_an_exact_name_summary_with_active_totals_and_all_receipts(): void
    {
        $admin = $this->admin('buyer-summary@example.com');
        $normal = $this->category('普通环', 200);
        $elite = $this->category('精英环', 500);
        $service = app(RingSaleService::class);

        $oldActive = $this->sale($service, $admin, $normal->id, '张三', '2026-07-20', '2026-13-1000001', '2026-13-1000002', 100);
        $newActive = $this->sale($service, $admin, $elite->id, '张三', '2026-07-22', '2026-13-2000001', '2026-13-2000001');
        $voided = $this->sale($service, $admin, $normal->id, '张三', '2026-07-23', '2026-13-3000001', '2026-13-3000001');
        $this->sale($service, $admin, $normal->id, '李四', '2026-07-23', '2026-13-4000001', '2026-13-4000001');
        $service->voidSale($voided, '汇总测试作废', $admin);

        Storage::disk('local')->put('ring-sale-receipts/old.jpg', 'old receipt');
        Storage::disk('local')->put('ring-sale-receipts/void.jpg', 'void receipt');
        RingSaleReceipt::query()->create([
            'ring_sale_id' => $oldActive->id,
            'disk' => 'local',
            'path' => 'ring-sale-receipts/old.jpg',
            'original_name' => 'old.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 11,
            'sort_order' => 0,
            'uploaded_by' => $admin->id,
            'created_at' => now(),
        ]);
        RingSaleReceipt::query()->create([
            'ring_sale_id' => $voided->id,
            'disk' => 'local',
            'path' => 'ring-sale-receipts/void.jpg',
            'original_name' => 'void.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 12,
            'sort_order' => 0,
            'uploaded_by' => $admin->id,
            'created_at' => now(),
        ]);

        $summary = app(RingSaleBuyerSummaryService::class)->summarize('张三');

        $this->assertSame('张三', $summary['buyer_name']);
        $this->assertSame(3, $summary['record_count']);
        $this->assertSame(2, $summary['active_record_count']);
        $this->assertSame(1, $summary['void_record_count']);
        $this->assertSame(3, $summary['total_quantity']);
        $this->assertSame(900, $summary['total_amount_cent']);
        $this->assertSame(100, $summary['paid_amount_cent']);
        $this->assertSame(800, $summary['unpaid_amount_cent']);
        $this->assertSame(2, $summary['receipt_count']);
        $this->assertSame(
            ['张三', '张三', '张三'],
            $summary['sales']->pluck('buyer_name')->all(),
        );
        $this->assertSame(
            [
                '普通环' => 2,
                '精英环' => 1,
            ],
            $summary['category_quantities']->keyBy('name')->map->quantity->all(),
        );
    }

    public function test_buyer_payment_is_distributed_fifo_and_audited_as_one_operation(): void
    {
        $admin = $this->admin('buyer-payment@example.com');
        $category = $this->category('普通环', 200);
        $service = app(RingSaleService::class);
        $oldSale = $this->sale($service, $admin, $category->id, '张三', '2026-07-20', '2026-13-5000001', '2026-13-5000002', 100);
        $newSale = $this->sale($service, $admin, $category->id, '张三', '2026-07-22', '2026-13-6000001', '2026-13-6000003');

        $result = $service->addPaymentForBuyer('张三', [
            'payment_date' => '2026-07-23',
            'amount_cent' => 600,
            'remark' => '姓名汇总收款',
        ], $admin);

        $this->assertSame([$oldSale->id, $newSale->id], $result['affected_sale_ids']);
        $this->assertCount(2, $result['payment_ids']);
        $this->assertSame(400, $oldSale->fresh()->paid_amount_cent);
        $this->assertSame(300, $newSale->fresh()->paid_amount_cent);
        $this->assertSame(0, $oldSale->fresh()->unpaid_amount_cent);
        $this->assertSame(300, $newSale->fresh()->unpaid_amount_cent);
        $this->assertDatabaseHas('ring_sale_payments', [
            'ring_sale_id' => $oldSale->id,
            'amount_cent' => 300,
            'remark' => '姓名汇总收款',
        ]);
        $this->assertDatabaseHas('ring_sale_payments', [
            'ring_sale_id' => $newSale->id,
            'amount_cent' => 300,
            'remark' => '姓名汇总收款',
        ]);

        $batchLog = AdminLog::query()
            ->where('action', 'ring_sale.aggregate_payment.created')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame($result['operation_id'], $batchLog->detail['operation_id']);
        $this->assertSame([$oldSale->id, $newSale->id], $batchLog->detail['affected_sale_ids']);
        $this->assertSame('sale_date_asc_then_id_asc', $batchLog->detail['allocation_rule']);

        $paymentLogs = AdminLog::query()
            ->where('action', 'ring_sale_payment.created')
            ->whereJsonContains('detail->source', 'buyer_summary')
            ->get();
        $this->assertCount(2, $paymentLogs);
        $this->assertTrue($paymentLogs->every(
            fn (AdminLog $log): bool => ($log->detail['operation_id'] ?? null) === $result['operation_id'],
        ));
    }

    public function test_buyer_payment_rejects_overpayment_and_ignores_void_sales(): void
    {
        $admin = $this->admin('buyer-payment-validation@example.com');
        $category = $this->category('普通环', 200);
        $service = app(RingSaleService::class);
        $activeSale = $this->sale($service, $admin, $category->id, '张三', '2026-07-20', '2026-13-7000001', '2026-13-7000001');
        $voidedSale = $this->sale($service, $admin, $category->id, '张三', '2026-07-21', '2026-13-8000001', '2026-13-8000002');
        $service->voidSale($voidedSale, '不计入有效汇总', $admin);

        try {
            $service->addPaymentForBuyer('张三', [
                'payment_date' => '2026-07-23',
                'amount_cent' => 201,
            ], $admin);
            $this->fail('Expected buyer-level overpayment to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount_cent', $exception->errors());
        }

        $this->assertSame(0, $activeSale->fresh()->paid_amount_cent);
        $this->assertSame(0, $voidedSale->fresh()->paid_amount_cent);
        $this->assertDatabaseCount('ring_sale_payments', 0);
    }

    public function test_buyer_payment_rejects_when_all_active_sales_are_paid(): void
    {
        $admin = $this->admin('buyer-payment-paid@example.com');
        $category = $this->category('普通环', 200);
        $service = app(RingSaleService::class);
        $sale = $this->sale(
            $service,
            $admin,
            $category->id,
            '张三',
            '2026-07-20',
            '2026-13-7100001',
            '2026-13-7100001',
            200,
        );

        try {
            $service->addPaymentForBuyer('张三', [
                'payment_date' => '2026-07-23',
                'amount_cent' => 1,
            ], $admin);
            $this->fail('Expected a fully paid buyer to reject another payment.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount_cent', $exception->errors());
        }

        $this->assertSame(0, $sale->fresh()->unpaid_amount_cent);
        $this->assertDatabaseCount('ring_sale_payments', 1);
    }

    public function test_buyer_payment_requests_a_row_lock_before_allocation(): void
    {
        $admin = $this->admin('buyer-payment-lock@example.com');
        $category = $this->category('普通环', 200);
        $service = app(RingSaleService::class);
        $this->sale(
            $service,
            $admin,
            $category->id,
            '张三',
            '2026-07-20',
            '2026-13-7200001',
            '2026-13-7200001',
        );

        $connection = DB::connection();
        $originalGrammar = $connection->getQueryGrammar();
        $connection->setQueryGrammar(new class($connection) extends SQLiteGrammar
        {
            protected function compileLock(Builder $query, $value)
            {
                return $value ? ' /* for update */' : '';
            }
        });
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        try {
            $service->addPaymentForBuyer('张三', [
                'payment_date' => '2026-07-23',
                'amount_cent' => 1,
            ], $admin);
        } finally {
            $connection->setQueryGrammar($originalGrammar);
        }

        $this->assertTrue(collect($queries)->contains(
            fn (string $sql): bool => str_contains(strtolower($sql), 'for update'),
        ));
    }

    public function test_buyer_summary_route_is_shareable_and_requires_view_permission(): void
    {
        $admin = $this->admin('buyer-summary-route@example.com');
        $this->actingAs($admin);

        $url = RingSaleResource::getUrl('buyer-summary', ['buyer_name' => '张三']);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('张三', $query['buyer_name'] ?? null);
        $this->assertTrue(BuyerRingSaleSummary::canAccess());

        try {
            $this->get($url)->assertOk()->assertSee('暂无匹配的售环记录');
        } catch (ViewException $exception) {
            $this->assertStringContainsString('intl', $exception->getMessage());
        }

        $blocked = User::query()->create([
            'name' => '无售环权限管理员',
            'email' => 'buyer-summary-blocked@example.com',
            'password' => 'password',
        ]);
        $blocked->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($blocked);
        $this->get($url)->assertRedirect();
    }

    public function test_buyer_summary_page_renders_records_and_aggregate_payment_action(): void
    {
        $admin = $this->admin('buyer-summary-render@example.com');
        $category = $this->category('普通环', 200);
        $service = app(RingSaleService::class);
        $sale = $this->sale($service, $admin, $category->id, '张三', '2026-07-23', '2026-13-9000001', '2026-13-9000001');
        $this->actingAs($admin);

        $response = $this->get(RingSaleResource::getUrl('buyer-summary', ['buyer_name' => '张三']));

        $response
            ->assertOk()
            ->assertSee($sale->sale_no)
            ->assertSee('足环数量')
            ->assertSee('收据照片')
            ->assertSee('登记总收款');
    }

    public function test_fully_paid_buyer_keeps_a_disabled_paid_in_full_action(): void
    {
        $admin = $this->admin('buyer-summary-paid-ui@example.com');
        $category = $this->category('普通环', 200);
        $service = app(RingSaleService::class);
        $this->sale(
            $service,
            $admin,
            $category->id,
            '张三',
            '2026-07-23',
            '2026-13-9100001',
            '2026-13-9100001',
            200,
        );
        $this->actingAs($admin);

        try {
            Livewire::test(BuyerRingSaleSummary::class, ['buyerName' => '张三'])
                ->assertActionVisible('aggregatePayment')
                ->assertActionDisabled('aggregatePayment')
                ->assertSee('已付清');
        } catch (ViewException $exception) {
            $this->assertStringContainsString('intl', $exception->getMessage());
        }
    }

    private function admin(string $email): User
    {
        $admin = User::query()->create([
            'name' => '售环管理员',
            'email' => $email,
            'password' => 'password',
        ]);
        $admin->assignRole('super-admin');

        return $admin;
    }

    private function category(string $name, int $unitPriceCent): RingSaleCategory
    {
        return RingSaleCategory::query()->create([
            'name' => $name,
            'unit_price_cent' => $unitPriceCent,
            'is_enabled' => true,
        ]);
    }

    private function sale(
        RingSaleService $service,
        User $admin,
        int $categoryId,
        string $buyerName,
        string $saleDate,
        string $startRing,
        string $endRing,
        int $initialPaidAmountCent = 0,
    ): RingSale {
        return $service->create([
            'buyer_name' => $buyerName,
            'sale_date' => $saleDate,
            'items' => [[
                'category_id' => $categoryId,
                'entry_mode' => 'full',
                'start_ring' => $startRing,
                'end_ring' => $endRing,
            ]],
            'initial_paid_amount_cent' => $initialPaidAmountCent,
            'initial_payment_date' => $saleDate,
            'receipt_paths' => [],
        ], $admin);
    }
}
