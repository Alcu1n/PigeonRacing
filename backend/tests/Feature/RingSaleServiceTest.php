<?php

// [IN]: Ring-sale commands, members, categories, prefixes, and payments / 售环命令、会员、类别、前缀与收款
// [OUT]: Transactional ring-sale persistence and financial invariants / 事务化售环持久化与金额约束
// [POS]: Ring-sale service feature tests / 售环服务功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Models\Member;
use App\Models\RingNumberPrefix;
use App\Models\RingSale;
use App\Models\RingSaleCategory;
use App\Models\RingSalePayment;
use App\Models\User;
use App\Services\RingSaleService;
use App\Services\RingSaleSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RingSaleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_one_sale_with_multiple_segments_and_an_initial_payment(): void
    {
        $admin = User::query()->create([
            'name' => '售环管理员',
            'email' => 'rings@example.com',
            'password' => 'password',
        ]);
        $member = Member::query()->create([
            'loft_number' => 'A001',
            'participant_name' => '张三',
            'status' => 'enabled',
        ]);
        $normal = RingSaleCategory::query()->create([
            'name' => '普通环',
            'unit_price_cent' => 200,
            'is_enabled' => true,
        ]);
        $elite = RingSaleCategory::query()->create([
            'name' => '小精英',
            'unit_price_cent' => 500,
            'is_enabled' => true,
        ]);
        $prefix = RingNumberPrefix::query()->create([
            'prefix' => '2026-13-055',
            'suffix_width' => 4,
            'is_enabled' => true,
        ]);

        $sale = app(RingSaleService::class)->create([
            'member_id' => $member->id,
            'buyer_name' => '张三',
            'loft_number' => 'A001',
            'sale_date' => '2026-07-23',
            'remark' => '现场领取',
            'items' => [
                [
                    'category_id' => $normal->id,
                    'entry_mode' => 'prefix',
                    'prefix_id' => $prefix->id,
                    'start_suffix' => '1',
                    'end_suffix' => '3',
                ],
                [
                    'category_id' => $elite->id,
                    'entry_mode' => 'full',
                    'start_ring' => '2026-13-0770001',
                    'end_ring' => '2026-13-0770002',
                ],
            ],
            'initial_paid_amount_cent' => 800,
            'initial_payment_date' => '2026-07-23',
            'initial_payment_remark' => '现金',
            'receipt_paths' => [],
        ], $admin);

        $this->assertSame('SH20260723-'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT), $sale->sale_no);
        $this->assertSame(5, $sale->total_quantity);
        $this->assertSame(1600, $sale->total_amount_cent);
        $this->assertSame(800, $sale->paid_amount_cent);
        $this->assertSame(800, $sale->unpaid_amount_cent);
        $this->assertSame('部分付款', $sale->payment_status_label);
        $this->assertSame(2, $sale->items()->count());
        $this->assertSame(5, $sale->allocations()->count());
        $this->assertSame('普通环', $sale->items()->orderBy('sort_order')->firstOrFail()->category_name_snapshot);
        $this->assertDatabaseHas('ring_sale_payments', [
            'ring_sale_id' => $sale->id,
            'amount_cent' => 800,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $admin->id,
            'action' => 'ring_sale.created',
            'target_id' => $sale->id,
        ]);
        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $admin->id,
            'action' => 'ring_sale_payment.created',
            'target_type' => RingSalePayment::class,
        ]);

        $member->update(['participant_name' => '改名后的会员', 'loft_number' => 'B999']);
        $this->assertSame('张三', $sale->fresh()->buyer_name);
        $this->assertSame('A001', $sale->fresh()->loft_number);
    }

    public function test_it_blocks_cross_mode_duplicates_and_releases_numbers_after_voiding(): void
    {
        $admin = User::query()->create([
            'name' => '售环管理员',
            'email' => 'duplicate-rings@example.com',
            'password' => 'password',
        ]);
        $category = RingSaleCategory::query()->create([
            'name' => '普通环',
            'unit_price_cent' => 200,
            'is_enabled' => true,
        ]);
        $prefix = RingNumberPrefix::query()->create([
            'prefix' => '2026-13-055',
            'suffix_width' => 4,
            'is_enabled' => true,
        ]);
        $service = app(RingSaleService::class);

        $first = $service->create($this->salePayload($category->id, [
            'category_id' => $category->id,
            'entry_mode' => 'prefix',
            'prefix_id' => $prefix->id,
            'start_suffix' => '1',
            'end_suffix' => '2',
        ]), $admin);

        try {
            $service->create($this->salePayload($category->id, [
                'category_id' => $category->id,
                'entry_mode' => 'full',
                'start_ring' => '2026-13-0550002',
                'end_ring' => '2026-13-0550003',
            ]), $admin);
            $this->fail('Expected duplicate range validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }

        $service->voidSale($first, '原订单录入错误', $admin);
        $this->assertTrue(
            RingSale::query()
                ->whereKey($first->id)
                ->containingRing('2026-13-0550002')
                ->exists(),
        );

        $replacement = $service->create($this->salePayload($category->id, [
            'category_id' => $category->id,
            'entry_mode' => 'full',
            'start_ring' => '2026-13-0550002',
            'end_ring' => '2026-13-0550003',
        ]), $admin);

        $this->assertSame('void', $first->refresh()->status);
        $this->assertSame(2, $replacement->allocations()->count());
    }

    public function test_payment_edits_cannot_overpay_and_voided_payments_leave_the_total(): void
    {
        $admin = User::query()->create([
            'name' => '收款管理员',
            'email' => 'payment-rings@example.com',
            'password' => 'password',
        ]);
        $category = RingSaleCategory::query()->create([
            'name' => '普通环',
            'unit_price_cent' => 200,
            'is_enabled' => true,
        ]);
        $service = app(RingSaleService::class);
        $sale = $service->create($this->salePayload($category->id, [
            'category_id' => $category->id,
            'entry_mode' => 'full',
            'start_ring' => '2026-13-0880001',
            'end_ring' => '2026-13-0880002',
        ]), $admin);

        $payment = $service->addPayment($sale, [
            'payment_date' => '2026-07-23',
            'amount_cent' => 300,
            'remark' => '现金',
        ], $admin);

        try {
            $service->updatePayment($payment, [
                'payment_date' => '2026-07-23',
                'amount_cent' => 500,
                'remark' => '误填',
            ], $admin);
            $this->fail('Expected overpayment validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount_cent', $exception->errors());
        }

        $this->assertSame(300, $payment->refresh()->amount_cent);
        $service->voidPayment($payment, '重复登记', $admin);

        $this->assertSame(0, $sale->fresh()->paid_amount_cent);
        $this->assertSame('未付款', $sale->fresh()->payment_status_label);
    }

    public function test_used_categories_and_prefixes_lock_pricing_fields_but_can_be_disabled(): void
    {
        $admin = User::query()->create([
            'name' => '配置管理员',
            'email' => 'config-rings@example.com',
            'password' => 'password',
        ]);
        $category = RingSaleCategory::query()->create([
            'name' => '普通环',
            'unit_price_cent' => 200,
            'is_enabled' => true,
        ]);
        $prefix = RingNumberPrefix::query()->create([
            'prefix' => '2026-13-099',
            'suffix_width' => 4,
            'is_enabled' => true,
        ]);
        app(RingSaleService::class)->create($this->salePayload($category->id, [
            'category_id' => $category->id,
            'entry_mode' => 'prefix',
            'prefix_id' => $prefix->id,
            'start_suffix' => '1',
            'end_suffix' => '1',
        ]), $admin);

        $category->update(['is_enabled' => false]);
        $prefix->update(['is_enabled' => false]);

        $this->assertFalse($category->fresh()->is_enabled);
        $this->assertFalse($prefix->fresh()->is_enabled);

        try {
            $category->update(['unit_price_cent' => 300]);
            $this->fail('Expected used category price to be locked.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('name', $exception->errors());
        }

        try {
            $prefix->update(['suffix_width' => 5]);
            $this->fail('Expected used prefix width to be locked.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('prefix', $exception->errors());
        }
    }

    public function test_category_price_and_prefix_width_business_limits_are_enforced(): void
    {
        try {
            RingSaleCategory::query()->create([
                'name' => '零元类别',
                'unit_price_cent' => 0,
                'is_enabled' => true,
            ]);
            $this->fail('Expected a zero category price to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('unit_price_cent', $exception->errors());
        }

        try {
            RingNumberPrefix::query()->create([
                'prefix' => '2026-13-INVALID',
                'suffix_width' => 13,
                'is_enabled' => true,
            ]);
            $this->fail('Expected an oversized suffix width to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('suffix_width', $exception->errors());
        }
    }

    public function test_edit_rebuilds_allocations_and_rejects_a_total_below_active_payments(): void
    {
        $admin = User::query()->create([
            'name' => '编辑管理员',
            'email' => 'edit-rings@example.com',
            'password' => 'password',
        ]);
        $category = RingSaleCategory::query()->create([
            'name' => '普通环',
            'unit_price_cent' => 200,
            'is_enabled' => true,
        ]);
        $service = app(RingSaleService::class);
        $sale = $service->create($this->salePayload($category->id, [
            'category_id' => $category->id,
            'entry_mode' => 'full',
            'start_ring' => '2026-13-3000001',
            'end_ring' => '2026-13-3000002',
        ]), $admin);
        $service->addPayment($sale, [
            'payment_date' => '2026-07-23',
            'amount_cent' => 300,
        ], $admin);

        try {
            $service->update($sale, $this->salePayload($category->id, [
                'category_id' => $category->id,
                'entry_mode' => 'full',
                'start_ring' => '2026-13-3000003',
                'end_ring' => '2026-13-3000003',
            ]), $admin);
            $this->fail('Expected a lower total than active payments to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }

        $this->assertSame(
            ['2026-13-3000001', '2026-13-3000002'],
            $sale->allocations()->orderBy('display_ring_number')->pluck('display_ring_number')->all(),
        );

        $updated = $service->update($sale, $this->salePayload($category->id, [
            'category_id' => $category->id,
            'entry_mode' => 'full',
            'start_ring' => '2026-13-3000003',
            'end_ring' => '2026-13-3000004',
        ]), $admin);

        $this->assertSame(
            ['2026-13-3000003', '2026-13-3000004'],
            $updated->allocations()->orderBy('display_ring_number')->pluck('display_ring_number')->all(),
        );

        $replacement = $service->create($this->salePayload($category->id, [
            'category_id' => $category->id,
            'entry_mode' => 'full',
            'start_ring' => '2026-13-3000001',
            'end_ring' => '2026-13-3000002',
        ]), $admin);
        $this->assertSame(2, $replacement->allocations()->count());
    }

    public function test_future_sale_and_payment_dates_are_rejected(): void
    {
        $admin = User::query()->create([
            'name' => '日期管理员',
            'email' => 'date-rings@example.com',
            'password' => 'password',
        ]);
        $category = RingSaleCategory::query()->create([
            'name' => '普通环',
            'unit_price_cent' => 200,
            'is_enabled' => true,
        ]);
        $service = app(RingSaleService::class);

        try {
            $service->create([
                ...$this->salePayload($category->id, [
                    'category_id' => $category->id,
                    'entry_mode' => 'full',
                    'start_ring' => '2026-13-4000001',
                    'end_ring' => '2026-13-4000001',
                ]),
                'sale_date' => '2026-07-24',
            ], $admin);
            $this->fail('Expected future sale date to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('sale_date', $exception->errors());
        }

        $sale = $service->create($this->salePayload($category->id, [
            'category_id' => $category->id,
            'entry_mode' => 'full',
            'start_ring' => '2026-13-4000001',
            'end_ring' => '2026-13-4000001',
        ]), $admin);

        try {
            $service->addPayment($sale, [
                'payment_date' => '2026-07-24',
                'amount_cent' => 100,
            ], $admin);
            $this->fail('Expected future payment date to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment_date', $exception->errors());
        }

        try {
            $service->addPayment($sale, ['amount_cent' => 100], $admin);
            $this->fail('Expected missing payment date to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment_date', $exception->errors());
        }
    }

    public function test_filtered_summary_counts_only_active_sales_and_active_payments(): void
    {
        $admin = User::query()->create([
            'name' => '汇总管理员',
            'email' => 'summary-rings@example.com',
            'password' => 'password',
        ]);
        $category = RingSaleCategory::query()->create([
            'name' => '普通环',
            'unit_price_cent' => 200,
            'is_enabled' => true,
        ]);
        $service = app(RingSaleService::class);
        $active = $service->create([
            ...$this->salePayload($category->id, [
                'category_id' => $category->id,
                'entry_mode' => 'full',
                'start_ring' => '2026-13-1000001',
                'end_ring' => '2026-13-1000002',
            ]),
            'buyer_name' => '筛选目标',
            'initial_paid_amount_cent' => 200,
            'initial_payment_date' => '2026-07-23',
        ], $admin);
        $voided = $service->create([
            ...$this->salePayload($category->id, [
                'category_id' => $category->id,
                'entry_mode' => 'full',
                'start_ring' => '2026-13-2000001',
                'end_ring' => '2026-13-2000003',
            ]),
            'buyer_name' => '筛选目标',
        ], $admin);
        $service->voidSale($voided, '测试作废', $admin);
        $service->addPayment($active, [
            'payment_date' => '2026-07-23',
            'amount_cent' => 200,
        ], $admin);

        $summary = app(RingSaleSummaryService::class)->summarize(
            RingSale::query()->where('buyer_name', '筛选目标'),
        );

        $this->assertSame([
            'sales' => 1,
            'quantity' => 2,
            'total_amount_cent' => 400,
            'paid_amount_cent' => 400,
            'unpaid_amount_cent' => 0,
        ], $summary);
    }

    /** @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function salePayload(int $categoryId, array $item): array
    {
        return [
            'buyer_name' => '测试购买人',
            'sale_date' => '2026-07-23',
            'items' => [[
                ...$item,
                'category_id' => $categoryId,
            ]],
            'initial_paid_amount_cent' => 0,
            'receipt_paths' => [],
        ];
    }
}
