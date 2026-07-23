<?php

// [IN]: Trusted administrator ring-sale, payment, receipt, and void commands / 可信管理员售环、收款、收据与作废命令
// [OUT]: Transactional sale totals, allocations, payment invariants, and audit logs / 事务化售环金额、号码占用、收款约束与审计日志
// [POS]: Ring-sale aggregate workflow service / 售环聚合工作流服务
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Services;

use App\Models\AdminLog;
use App\Models\RingNumberPrefix;
use App\Models\RingSale;
use App\Models\RingSaleAllocation;
use App\Models\RingSaleCategory;
use App\Models\RingSaleItem;
use App\Models\RingSalePayment;
use App\Models\User;
use App\Support\RingNumberRange;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class RingSaleService
{
    /** @param array<string, mixed> $data */
    public function create(array $data, User $admin): RingSale
    {
        $normalized = $this->normalizeSale($data, creating: true);

        try {
            return DB::transaction(function () use ($normalized, $data, $admin): RingSale {
                $sale = RingSale::query()->create([
                    'member_id' => $data['member_id'] ?? null,
                    'buyer_name' => trim((string) ($data['buyer_name'] ?? '')),
                    'loft_number' => $this->nullableString($data['loft_number'] ?? null),
                    'sale_date' => $normalized['sale_date'],
                    'total_quantity' => $normalized['total_quantity'],
                    'total_amount_cent' => $normalized['total_amount_cent'],
                    'status' => 'active',
                    'remark' => $this->nullableString($data['remark'] ?? null),
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ]);

                $sale->update([
                    'sale_no' => 'SH'.$sale->sale_date->format('Ymd').'-'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT),
                ]);

                $this->replaceItems($sale, $normalized['items']);

                $initialPaid = (int) ($data['initial_paid_amount_cent'] ?? 0);
                if ($initialPaid > 0) {
                    $payment = $this->createPaymentRow($sale, [
                        'payment_date' => $data['initial_payment_date'] ?? $normalized['sale_date'],
                        'amount_cent' => $initialPaid,
                        'remark' => $data['initial_payment_remark'] ?? null,
                    ], $admin);
                    $this->audit($admin, 'ring_sale_payment.created', $payment, [
                        'ring_sale_id' => $sale->id,
                        'amount_cent' => $payment->amount_cent,
                        'payment_date' => $payment->payment_date->toDateString(),
                        'source' => 'initial_payment',
                    ]);
                }

                $this->syncReceipts($sale, (array) ($data['receipt_paths'] ?? []), $admin);
                $this->assertPaymentTotal($sale);
                $this->audit($admin, 'ring_sale.created', $sale, [
                    'sale_no' => $sale->sale_no,
                    'buyer_name' => $sale->buyer_name,
                    'total_quantity' => $sale->total_quantity,
                    'total_amount_cent' => $sale->total_amount_cent,
                ]);

                return $sale->load(['items', 'payments', 'receipts']);
            });
        } catch (QueryException $exception) {
            $this->throwFriendlyDuplicate($exception);
        }
    }

    /** @param array<string, mixed> $data */
    public function update(RingSale $sale, array $data, User $admin): RingSale
    {
        if ($sale->status === 'void') {
            throw ValidationException::withMessages(['sale' => '作废售环单不能编辑。']);
        }

        $normalized = $this->normalizeSale($data, creating: false);
        try {
            return DB::transaction(function () use ($sale, $data, $normalized, $admin): RingSale {
                $sale = RingSale::query()->lockForUpdate()->findOrFail($sale->id);
                if ($sale->status === 'void') {
                    throw ValidationException::withMessages(['sale' => '作废售环单不能编辑。']);
                }

                $before = $sale->only([
                    'member_id',
                    'buyer_name',
                    'loft_number',
                    'sale_date',
                    'total_quantity',
                    'total_amount_cent',
                    'remark',
                ]);
                $before['items'] = $sale->items()
                    ->get(['category_name_snapshot', 'unit_price_cent', 'start_ring', 'end_ring', 'quantity', 'line_amount_cent'])
                    ->toArray();
                $activePaid = (int) $sale->payments()->where('status', 'active')->sum('amount_cent');
                if ($activePaid > $normalized['total_amount_cent']) {
                    throw ValidationException::withMessages([
                        'items' => '修改后的总金额不能低于当前有效收款合计，请先调整或作废收款流水。',
                    ]);
                }

                $sale->update([
                    'member_id' => $data['member_id'] ?? null,
                    'buyer_name' => trim((string) ($data['buyer_name'] ?? '')),
                    'loft_number' => $this->nullableString($data['loft_number'] ?? null),
                    'sale_date' => $normalized['sale_date'],
                    'total_quantity' => $normalized['total_quantity'],
                    'total_amount_cent' => $normalized['total_amount_cent'],
                    'remark' => $this->nullableString($data['remark'] ?? null),
                    'updated_by' => $admin->id,
                ]);

                $sale->update([
                    'sale_no' => 'SH'.$sale->sale_date->format('Ymd').'-'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT),
                ]);

                $sale->items()->delete();
                $this->replaceItems($sale, $normalized['items']);
                $this->syncReceipts($sale, (array) ($data['receipt_paths'] ?? []), $admin);
                $after = $sale->only([
                    'member_id',
                    'buyer_name',
                    'loft_number',
                    'sale_date',
                    'total_quantity',
                    'total_amount_cent',
                    'remark',
                ]);
                $after['items'] = $sale->items()
                    ->get(['category_name_snapshot', 'unit_price_cent', 'start_ring', 'end_ring', 'quantity', 'line_amount_cent'])
                    ->toArray();
                $this->audit($admin, 'ring_sale.updated', $sale, [
                    'before' => $before,
                    'after' => $after,
                ]);

                return $sale->load(['items', 'payments', 'receipts']);
            });
        } catch (QueryException $exception) {
            $this->throwFriendlyDuplicate($exception);
        }
    }

    /** @param array<string, mixed> $data */
    public function addPayment(RingSale $sale, array $data, User $admin): RingSalePayment
    {
        return DB::transaction(function () use ($sale, $data, $admin): RingSalePayment {
            $sale = RingSale::query()->lockForUpdate()->findOrFail($sale->id);
            if ($sale->status === 'void') {
                throw ValidationException::withMessages(['payment' => '作废售环单不能登记收款。']);
            }

            $payment = $this->createPaymentRow($sale, $data, $admin);
            $this->assertPaymentTotal($sale);
            $this->audit($admin, 'ring_sale_payment.created', $payment, [
                'ring_sale_id' => $sale->id,
                'amount_cent' => $payment->amount_cent,
                'payment_date' => $payment->payment_date->toDateString(),
            ]);

            return $payment;
        });
    }

    /** @param array<string, mixed> $data */
    public function updatePayment(RingSalePayment $payment, array $data, User $admin): RingSalePayment
    {
        $normalized = $this->normalizePayment($data);

        return DB::transaction(function () use ($payment, $normalized, $admin): RingSalePayment {
            $sale = RingSale::query()->lockForUpdate()->findOrFail($payment->ring_sale_id);
            $payment = RingSalePayment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status === 'void' || $sale->status === 'void') {
                throw ValidationException::withMessages(['payment' => '作废记录不能编辑收款。']);
            }

            $before = $payment->only(['payment_date', 'amount_cent', 'remark']);
            $payment->update([
                ...$normalized,
                'updated_by' => $admin->id,
            ]);
            $this->assertPaymentTotal($sale);
            $this->audit($admin, 'ring_sale_payment.updated', $payment, [
                'before' => $before,
                'after' => $payment->only(array_keys($before)),
            ]);

            return $payment->refresh();
        });
    }

    public function voidPayment(RingSalePayment $payment, string $reason, User $admin): RingSalePayment
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['void_reason' => '请填写收款流水作废原因。']);
        }

        return DB::transaction(function () use ($payment, $reason, $admin): RingSalePayment {
            RingSale::query()->lockForUpdate()->findOrFail($payment->ring_sale_id);
            $payment = RingSalePayment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status === 'void') {
                return $payment;
            }

            $payment->update([
                'status' => 'void',
                'void_reason' => $reason,
                'voided_at' => now(),
                'voided_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
            $this->audit($admin, 'ring_sale_payment.voided', $payment, [
                'ring_sale_id' => $payment->ring_sale_id,
                'amount_cent' => $payment->amount_cent,
                'reason' => $reason,
            ]);

            return $payment->refresh();
        });
    }

    public function voidSale(RingSale $sale, string $reason, User $admin): RingSale
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['void_reason' => '请填写售环单作废原因。']);
        }

        return DB::transaction(function () use ($sale, $reason, $admin): RingSale {
            $sale = RingSale::query()->lockForUpdate()->findOrFail($sale->id);
            if ($sale->status === 'void') {
                return $sale;
            }

            RingSaleAllocation::query()
                ->whereIn('ring_sale_item_id', $sale->items()->select('id'))
                ->delete();

            $sale->update([
                'status' => 'void',
                'void_reason' => $reason,
                'voided_at' => now(),
                'voided_by' => $admin->id,
                'updated_by' => $admin->id,
            ]);
            $this->audit($admin, 'ring_sale.voided', $sale, [
                'sale_no' => $sale->sale_no,
                'reason' => $reason,
                'total_amount_cent' => $sale->total_amount_cent,
                'paid_amount_cent' => $sale->paid_amount_cent,
            ]);

            return $sale->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{sale_date: string, total_quantity: int, total_amount_cent: int, items: array<int, array<string, mixed>>}
     */
    private function normalizeSale(array $data, bool $creating): array
    {
        $buyerName = trim((string) ($data['buyer_name'] ?? ''));
        if ($buyerName === '') {
            throw ValidationException::withMessages(['buyer_name' => '请填写购买人姓名。']);
        }

        $saleDate = $this->normalizeDate($data['sale_date'] ?? null, 'sale_date', '售环日期');
        $items = (array) ($data['items'] ?? []);
        if ($items === []) {
            throw ValidationException::withMessages(['items' => '请至少添加一个号码段。']);
        }

        $normalizedItems = [];
        $seenRings = [];
        $totalQuantity = 0;
        $totalAmount = 0;

        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                throw ValidationException::withMessages(['items' => '号码段格式无效。']);
            }

            $category = RingSaleCategory::query()->find($item['category_id'] ?? null);
            if (! $category || ($creating && ! $category->is_enabled)) {
                throw ValidationException::withMessages(["items.{$index}.category_id" => '请选择有效的足环类别。']);
            }

            try {
                [$range, $prefixId, $prefixSnapshot] = $this->rangeFromItem($item, $creating);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages(["items.{$index}" => $exception->getMessage()]);
            }

            foreach ($range->rings() as $ring) {
                $canonical = $range->canonical($ring);
                if (isset($seenRings[$canonical])) {
                    throw ValidationException::withMessages([
                        "items.{$index}" => "足环号码 {$ring} 在当前售环单中重复。",
                    ]);
                }
                $seenRings[$canonical] = true;
            }

            $quantity = $range->quantity();
            $lineAmount = $quantity * (int) $category->unit_price_cent;
            $totalQuantity += $quantity;
            $totalAmount += $lineAmount;
            $normalizedItems[] = [
                'ring_sale_category_id' => $category->id,
                'ring_number_prefix_id' => $prefixId,
                'entry_mode' => ($item['entry_mode'] ?? 'prefix') === 'full' ? 'full' : 'prefix',
                'category_name_snapshot' => $category->name,
                'unit_price_cent' => (int) $category->unit_price_cent,
                'prefix_snapshot' => $prefixSnapshot,
                'suffix_width' => $range->suffixWidth,
                'start_number' => $range->startNumber,
                'end_number' => $range->endNumber,
                'start_ring' => $range->startRing,
                'end_ring' => $range->endRing,
                'quantity' => $quantity,
                'line_amount_cent' => $lineAmount,
                'range' => $range,
                'sort_order' => $index,
            ];
        }

        $initialPaid = (int) ($data['initial_paid_amount_cent'] ?? 0);
        if ($initialPaid < 0 || $initialPaid > $totalAmount) {
            throw ValidationException::withMessages([
                'initial_paid_amount_cent' => '首付款不能小于 0 或超过售环总金额。',
            ]);
        }

        return [
            'sale_date' => $saleDate,
            'total_quantity' => $totalQuantity,
            'total_amount_cent' => $totalAmount,
            'items' => $normalizedItems,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{RingNumberRange, int|null, string|null}
     */
    private function rangeFromItem(array $item, bool $creating): array
    {
        if (($item['entry_mode'] ?? 'prefix') === 'full') {
            $range = RingNumberRange::fromFull(
                (string) ($item['start_ring'] ?? ''),
                (string) ($item['end_ring'] ?? ''),
            );

            return [
                $range,
                null,
                $range->numberPrefix,
            ];
        }

        $prefix = RingNumberPrefix::query()->find($item['prefix_id'] ?? null);
        if (! $prefix || ($creating && ! $prefix->is_enabled)) {
            throw new InvalidArgumentException('请选择有效的号码前缀。');
        }

        return [
            RingNumberRange::fromPrefix(
                $prefix->prefix,
                (int) $prefix->suffix_width,
                (string) ($item['start_suffix'] ?? ''),
                (string) ($item['end_suffix'] ?? ''),
            ),
            $prefix->id,
            $prefix->prefix,
        ];
    }

    /** @param array<int, array<string, mixed>> $items */
    private function replaceItems(RingSale $sale, array $items): void
    {
        foreach ($items as $data) {
            /** @var RingNumberRange $range */
            $range = $data['range'];
            unset($data['range']);

            /** @var RingSaleItem $item */
            $item = $sale->items()->create($data);
            $rows = [];

            foreach ($range->rings() as $ring) {
                $rows[] = [
                    'ring_sale_item_id' => $item->id,
                    'canonical_ring_number' => $range->canonical($ring),
                    'display_ring_number' => $ring,
                    'created_at' => now(),
                ];

                if (count($rows) === 500) {
                    RingSaleAllocation::query()->insert($rows);
                    $rows = [];
                }
            }

            if ($rows !== []) {
                RingSaleAllocation::query()->insert($rows);
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function createPaymentRow(RingSale $sale, array $data, User $admin): RingSalePayment
    {
        return $sale->payments()->create([
            ...$this->normalizePayment($data),
            'status' => 'active',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
    }

    /** @param array<string, mixed> $data
     * @return array{payment_date: string, amount_cent: int, remark: string|null}
     */
    private function normalizePayment(array $data): array
    {
        $amount = (int) ($data['amount_cent'] ?? 0);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount_cent' => '收款金额必须大于 0。']);
        }

        return [
            'payment_date' => $this->normalizeDate($data['payment_date'] ?? null, 'payment_date', '收款日期'),
            'amount_cent' => $amount,
            'remark' => $this->nullableString($data['remark'] ?? null),
        ];
    }

    private function assertPaymentTotal(RingSale $sale): void
    {
        $paid = (int) $sale->payments()->where('status', 'active')->sum('amount_cent');
        if ($paid > (int) $sale->total_amount_cent) {
            throw ValidationException::withMessages(['amount_cent' => '有效收款合计不能超过售环总金额。']);
        }
    }

    /** @param array<int, string> $paths */
    private function syncReceipts(RingSale $sale, array $paths, User $admin): void
    {
        $paths = array_values(array_unique(array_filter(array_map(
            fn (mixed $path): string => trim((string) $path),
            $paths,
        ))));

        if (count($paths) > 3) {
            throw ValidationException::withMessages(['receipt_paths' => '每笔售环单最多保存 3 张收据照片。']);
        }

        $existing = $sale->receipts()->pluck('path')->all();
        $removed = array_diff($existing, $paths);
        $sale->receipts()->whereIn('path', $removed)->delete();

        foreach ($removed as $path) {
            Storage::disk('local')->delete($path);
        }

        foreach ($paths as $index => $path) {
            if (! Storage::disk('local')->exists($path)) {
                throw ValidationException::withMessages(['receipt_paths' => '收据照片不存在或上传未完成。']);
            }

            $mime = Storage::disk('local')->mimeType($path);
            if (! in_array($mime, [
                'image/jpeg',
                'image/png',
                'image/webp',
                'image/heic',
                'image/heif',
            ], true)) {
                throw ValidationException::withMessages(['receipt_paths' => '收据仅支持 JPEG、PNG、WebP、HEIC 或 HEIF 图片。']);
            }

            if (Storage::disk('local')->size($path) > 10 * 1024 * 1024) {
                throw ValidationException::withMessages(['receipt_paths' => '每张收据照片不能超过 10 MB。']);
            }

            $sale->receipts()->updateOrCreate(
                ['path' => $path],
                [
                    'disk' => 'local',
                    'original_name' => basename($path),
                    'mime_type' => $mime,
                    'size' => Storage::disk('local')->size($path),
                    'sort_order' => $index,
                    'uploaded_by' => $admin->id,
                    'created_at' => now(),
                ],
            );
        }
    }

    private function normalizeDate(mixed $value, string $field, string $label): string
    {
        if (blank($value)) {
            throw ValidationException::withMessages([$field => "请填写{$label}。"]);
        }

        try {
            $date = Carbon::parse((string) $value)->startOfDay();
        } catch (Throwable) {
            throw ValidationException::withMessages([$field => "{$label}格式无效。"]);
        }

        if ($date->isAfter(today())) {
            throw ValidationException::withMessages([$field => "{$label}不能晚于今天。"]);
        }

        return $date->toDateString();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @param array<string, mixed> $detail */
    private function audit(User $admin, string $action, object $target, array $detail): void
    {
        AdminLog::query()->create([
            'admin_id' => $admin->id,
            'action' => $action,
            'target_type' => $target::class,
            'target_id' => $target->id,
            'detail' => $detail,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    private function throwFriendlyDuplicate(QueryException $exception): never
    {
        $message = mb_strtolower($exception->getMessage());
        if (str_contains($message, 'ring_sale_allocations')
            || str_contains($message, 'canonical_ring_number')
            || str_contains($message, 'unique constraint failed')) {
            throw ValidationException::withMessages([
                'items' => '号码段与已有有效售环记录冲突，请检查起止足环号。',
            ]);
        }

        throw $exception;
    }
}
