<?php

// [IN]: Authenticated administrators and private ring-sale receipt files / 已登录管理员与私有售环收据文件
// [OUT]: Permission-protected inline receipt responses / 受权限保护的收据内联响应
// [POS]: Ring-sale receipt access feature tests / 售环收据访问功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Models\RingSale;
use App\Models\RingSaleReceipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RingSaleReceiptAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_an_admin_with_ring_sale_view_permission_can_open_a_private_receipt(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ring-sale-receipts/receipt.jpg', 'private-image');

        $sale = RingSale::query()->create([
            'sale_no' => 'SH20260723-000001',
            'buyer_name' => '张三',
            'sale_date' => '2026-07-23',
            'status' => 'active',
        ]);
        $receipt = RingSaleReceipt::query()->create([
            'ring_sale_id' => $sale->id,
            'disk' => 'local',
            'path' => 'ring-sale-receipts/receipt.jpg',
            'original_name' => 'receipt.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 13,
            'created_at' => now(),
        ]);

        Role::findOrCreate('admin', 'web');
        Permission::findOrCreate('ring-sales.view', 'web');
        $allowed = User::query()->create([
            'name' => '可查看',
            'email' => 'receipt-view@example.com',
            'password' => 'password',
        ]);
        $allowed->assignRole('admin');
        $allowed->givePermissionTo('ring-sales.view');
        $denied = User::query()->create([
            'name' => '不可查看',
            'email' => 'receipt-denied@example.com',
            'password' => 'password',
        ]);
        $denied->assignRole('admin');

        $this->actingAs($denied)
            ->get(route('admin.ring-sale-receipts.show', $receipt))
            ->assertForbidden();

        $response = $this->actingAs($allowed)
            ->get(route('admin.ring-sale-receipts.show', $receipt))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $this->assertSame('private-image', $response->streamedContent());
    }
}
