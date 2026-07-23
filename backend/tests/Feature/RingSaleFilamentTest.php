<?php

// [IN]: Filament administrators and ring-sale clustered resources / Filament 管理员与售环模块资源
// [OUT]: Permission, route, tab, quick-entry, and export action assertions / 权限、路由、页签、快速录入与导出动作断言
// [POS]: Ring-sale Filament feature tests / 售环 Filament 功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Filament\Resources\RingNumberPrefixResource;
use App\Filament\Resources\RingSaleCategoryResource;
use App\Filament\Resources\RingSaleResource;
use App\Filament\Resources\RingSaleResource\Pages\ListRingSales;
use App\Models\RingNumberPrefix;
use App\Models\RingSaleCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\View\ViewException;
use Livewire\Livewire;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RingSaleFilamentTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_open_all_three_ring_sale_tabs_and_sees_quick_actions(): void
    {
        $admin = User::query()->create([
            'name' => '系统管理员',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $admin->assignRole('super-admin');
        $this->actingAs($admin);

        $this->get(RingSaleResource::getUrl())->assertOk()->assertSee('新增售环')->assertSee('导出 Excel');
        $this->get(RingSaleCategoryResource::getUrl())->assertOk()->assertSee('新增类别');
        $this->get(RingNumberPrefixResource::getUrl())->assertOk()->assertSee('新增前缀');

        $method = new ReflectionMethod(ListRingSales::class, 'getHeaderActions');
        $actions = collect($method->invoke(new ListRingSales))->keyBy(fn ($action): string => $action->getName());

        $this->assertSame('新增售环', $actions->get('createSale')?->getLabel());
        $this->assertSame('导出 Excel', $actions->get('exportExcel')?->getLabel());

        Livewire::test(ListRingSales::class)
            ->assertActionExists('createSale')
            ->assertActionVisible('createSale')
            ->mountAction('createSale')
            ->assertActionMounted('createSale')
            ->assertSchemaComponentExists('buyer_name')
            ->assertSchemaComponentExists('items')
            ->assertSchemaComponentExists('receipt_paths');
    }

    public function test_ordinary_admin_without_view_permission_cannot_open_ring_sales(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::query()->create([
            'name' => '普通管理员',
            'email' => 'ring-no-access@example.com',
            'password' => 'password',
        ]);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get('/admin/ring-sales/records')
            ->assertForbidden();
    }

    public function test_ring_sale_permission_catalog_contains_the_four_crud_actions(): void
    {
        $this->assertSame(
            [
                'ring-sales.create',
                'ring-sales.delete',
                'ring-sales.update',
                'ring-sales.view',
            ],
            Permission::query()
                ->where('name', 'like', 'ring-sales.%')
                ->orderBy('name')
                ->pluck('name')
                ->all(),
        );
    }

    public function test_view_only_admin_can_export_but_cannot_create_a_ring_sale(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::query()->create([
            'name' => '只读管理员',
            'email' => 'ring-readonly@example.com',
            'password' => 'password',
        ]);
        $admin->assignRole('admin');
        $admin->givePermissionTo('ring-sales.view');
        $this->actingAs($admin);

        Livewire::test(ListRingSales::class)
            ->assertActionHidden('createSale')
            ->assertActionVisible('exportExcel');
    }

    public function test_quick_entry_action_creates_a_sale_from_yuan_and_short_suffix_inputs(): void
    {
        $admin = User::query()->create([
            'name' => '录入管理员',
            'email' => 'ring-entry@example.com',
            'password' => 'password',
        ]);
        $admin->assignRole('super-admin');
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
        $this->actingAs($admin);

        $component = Livewire::test(ListRingSales::class)
            ->mountAction('createSale');
        $itemKey = array_key_first($component->get('mountedActions.0.data.items'));

        $component
            ->set('mountedActions.0.data.buyer_name', '张三')
            ->set('mountedActions.0.data.loft_number', '0008')
            ->set('mountedActions.0.data.sale_date', '2026-07-23')
            ->set("mountedActions.0.data.items.{$itemKey}.category_id", $category->id)
            ->set("mountedActions.0.data.items.{$itemKey}.entry_mode", 'prefix')
            ->set("mountedActions.0.data.items.{$itemKey}.prefix_id", $prefix->id)
            ->set("mountedActions.0.data.items.{$itemKey}.start_suffix", '1')
            ->set("mountedActions.0.data.items.{$itemKey}.end_suffix", '2')
            ->set('mountedActions.0.data.initial_paid_amount_cent', 2)
            ->set('mountedActions.0.data.initial_payment_date', '2026-07-23');

        try {
            $component->callMountedAction();
        } catch (ViewException $exception) {
            $this->assertStringContainsString('intl', $exception->getMessage());
        }

        $this->assertDatabaseHas('ring_sales', [
            'buyer_name' => '张三',
            'loft_number' => '0008',
            'total_quantity' => 2,
            'total_amount_cent' => 400,
        ]);
        $this->assertDatabaseHas('ring_sale_payments', ['amount_cent' => 200]);
    }
}
