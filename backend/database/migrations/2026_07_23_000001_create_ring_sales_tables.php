<?php

// [IN]: Laravel schema builder and ring-sale permission catalog / Laravel 数据库结构与售环权限目录
// [OUT]: Ring-sale ledger, ranges, payments, private receipt metadata, and allocations / 售环台账、号码段、收款、私有收据元数据与号码占用
// [POS]: Ring-sale module schema migration / 售环模块数据库迁移
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

use App\Support\AdminPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ring_sale_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('unit_price_cent');
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('ring_number_prefixes', function (Blueprint $table): void {
            $table->id();
            $table->string('prefix', 116);
            $table->unsignedTinyInteger('suffix_width')->default(4);
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
            $table->unique(['prefix', 'suffix_width']);
        });

        Schema::create('ring_sales', function (Blueprint $table): void {
            $table->id();
            $table->string('sale_no')->nullable()->unique();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('buyer_name')->index();
            $table->string('loft_number')->nullable()->index();
            $table->date('sale_date')->index();
            $table->unsignedInteger('total_quantity')->default(0);
            $table->unsignedBigInteger('total_amount_cent')->default(0);
            $table->string('status')->default('active')->index();
            $table->text('remark')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['sale_date', 'status']);
        });

        Schema::create('ring_sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ring_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ring_sale_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('ring_number_prefix_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('entry_mode', 16);
            $table->string('category_name_snapshot');
            $table->unsignedInteger('unit_price_cent');
            $table->string('prefix_snapshot', 116)->nullable();
            $table->unsignedTinyInteger('suffix_width')->nullable();
            $table->unsignedBigInteger('start_number');
            $table->unsignedBigInteger('end_number');
            $table->string('start_ring', 128)->index();
            $table->string('end_ring', 128);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('line_amount_cent');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ring_sale_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ring_sale_id')->constrained()->cascadeOnDelete();
            $table->date('payment_date')->index();
            $table->unsignedBigInteger('amount_cent');
            $table->string('status')->default('active')->index();
            $table->text('remark')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ring_sale_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ring_sale_id')->constrained()->cascadeOnDelete();
            $table->string('disk')->default('local');
            $table->string('path')->unique();
            $table->string('original_name');
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
        });

        Schema::create('ring_sale_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ring_sale_item_id')->constrained()->cascadeOnDelete();
            $table->string('canonical_ring_number', 128)->unique();
            $table->string('display_ring_number', 128);
            $table->timestamp('created_at');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (array_keys(AdminPermissions::ACTIONS) as $action) {
            Permission::findOrCreate(AdminPermissions::name('ring-sales', $action), 'web');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ring_sale_allocations');
        Schema::dropIfExists('ring_sale_receipts');
        Schema::dropIfExists('ring_sale_payments');
        Schema::dropIfExists('ring_sale_items');
        Schema::dropIfExists('ring_sales');
        Schema::dropIfExists('ring_number_prefixes');
        Schema::dropIfExists('ring_sale_categories');

        foreach (array_keys(AdminPermissions::ACTIONS) as $action) {
            Permission::query()
                ->where('name', AdminPermissions::name('ring-sales', $action))
                ->where('guard_name', 'web')
                ->delete();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
