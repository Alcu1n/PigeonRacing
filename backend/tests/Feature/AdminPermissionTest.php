<?php

// [IN]: Admin account records and permission assignments / 管理员账号记录与权限分配
// [OUT]: Backend administrator authorization behavior assertions / 后台管理员授权行为断言
// [POS]: Backend permission-management feature test / 后台权限管理功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Filament\Pages\EditAdminPassword;
use App\Filament\Resources\AdminUserResource;
use App\Filament\Resources\AdminUserResource\Pages\CreateAdminUser;
use App\Filament\Resources\AdminUserResource\Pages\EditAdminUser;
use App\Filament\Resources\MemberResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_has_every_permission_without_direct_assignment(): void
    {
        $superAdmin = User::query()->create([
            'name' => '系统管理员',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        Role::findOrCreate('super-admin', 'web');
        $superAdmin->assignRole('super-admin');

        $this->assertTrue($superAdmin->can('members.view'));
        $this->assertTrue($superAdmin->can('brand-settings.delete'));
    }

    public function test_ordinary_admin_can_only_open_assigned_module_and_never_permission_management(): void
    {
        $admin = User::query()->create([
            'name' => '赛事管理员',
            'phone' => '13800000000',
            'password' => 'password',
        ]);
        $admin->assignRole('admin');
        $this->actingAs($admin)
            ->get('/admin/members')
            ->assertForbidden();

        $this->actingAs($admin)
            ->get('/admin/brand-settings')
            ->assertForbidden();

        $this->actingAs($admin)
            ->get('/admin/admin-users')
            ->assertForbidden();

        Permission::findOrCreate('members.create', 'web');
        $admin->givePermissionTo('members.create');

        $this->get('/admin/members/create')->assertForbidden();

        Permission::findOrCreate('members.view', 'web');
        $admin->givePermissionTo('members.view');

        $this->assertTrue(MemberResource::canViewAny());
    }

    public function test_admin_can_log_in_with_phone_and_password_without_verification(): void
    {
        $admin = User::query()->create([
            'name' => '手机号管理员',
            'phone' => '13900000000',
            'password' => 'password',
        ]);
        $admin->assignRole('admin');

        Livewire::test(Login::class)
            ->fillForm([
                'account' => '13900000000',
                'password' => 'password',
                'remember' => false,
            ])
            ->call('authenticate')
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_can_log_in_with_email_and_password_without_verification(): void
    {
        $admin = User::query()->create([
            'name' => '邮箱管理员',
            'email' => 'operator@example.com',
            'password' => 'password',
        ]);
        $admin->assignRole('admin');

        Livewire::test(Login::class)
            ->fillForm([
                'account' => 'operator@example.com',
                'password' => 'password',
                'remember' => false,
            ])
            ->call('authenticate')
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_super_admin_cannot_be_edited_or_deleted_through_permission_management(): void
    {
        $superAdmin = User::query()->create([
            'name' => '系统管理员',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $superAdmin->assignRole('super-admin');
        $ordinaryAdmin = User::query()->create([
            'name' => '普通管理员',
            'phone' => '13700000000',
            'password' => 'password',
        ]);
        $ordinaryAdmin->assignRole('admin');

        $this->actingAs($superAdmin);

        $this->assertFalse(AdminUserResource::canEdit($superAdmin));
        $this->assertFalse(AdminUserResource::canDelete($superAdmin));
        $this->assertTrue(AdminUserResource::canEdit($ordinaryAdmin));
    }

    public function test_super_admin_can_create_and_update_an_ordinary_admin_with_direct_permissions(): void
    {
        $superAdmin = User::query()->create([
            'name' => '系统管理员',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $superAdmin->assignRole('super-admin');
        $this->actingAs($superAdmin);

        Livewire::test(CreateAdminUser::class)
            ->fillForm([
                'name' => '运营管理员',
                'phone' => '13600000000',
                'password' => 'initial-password',
                'permissions' => ['members.view'],
            ])
            ->call('create')
            ->assertHasNoErrors();

        $admin = User::query()->where('phone', '13600000000')->firstOrFail();

        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue($admin->can('members.view'));

        Livewire::test(EditAdminUser::class, ['record' => $admin->getKey()])
            ->fillForm([
                'name' => '赛事运营管理员',
                'phone' => '13600000000',
                'password' => 'reset-password',
                'permissions' => ['races.update'],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $admin->refresh();
        $this->assertSame('赛事运营管理员', $admin->name);
        $this->assertTrue(Hash::check('reset-password', $admin->password));
        $this->assertFalse($admin->can('members.view'));
        $this->assertTrue($admin->can('races.update'));
    }

    public function test_admin_phone_and_email_must_be_unique_and_at_least_one_must_be_present(): void
    {
        $superAdmin = User::query()->create([
            'name' => '系统管理员',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $superAdmin->assignRole('super-admin');
        User::query()->create([
            'name' => '既有管理员',
            'phone' => '13500000000',
            'email' => 'duplicate@example.com',
            'password' => 'password',
        ]);
        $this->actingAs($superAdmin);

        Livewire::test(CreateAdminUser::class)
            ->fillForm([
                'name' => '重复手机号管理员',
                'phone' => '13500000000',
                'password' => 'initial-password',
            ])
            ->call('create')
            ->assertHasFormErrors(['phone']);

        Livewire::test(CreateAdminUser::class)
            ->fillForm([
                'name' => '缺少账号管理员',
                'password' => 'initial-password',
            ])
            ->call('create')
            ->assertHasFormErrors(['phone', 'email']);
    }

    public function test_admin_must_verify_current_password_to_change_own_password(): void
    {
        $admin = User::query()->create([
            'name' => '改密管理员',
            'email' => 'password@example.com',
            'password' => 'old-password',
        ]);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Livewire::test(EditAdminPassword::class)
            ->fillForm([
                'current_password' => 'old-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('new-password', $admin->fresh()->password));
    }

    public function test_invalid_credentials_are_rejected_without_any_verification_step(): void
    {
        $admin = User::query()->create([
            'name' => '错误密码管理员',
            'email' => 'invalid@example.com',
            'password' => 'correct-password',
        ]);
        $admin->assignRole('admin');

        Livewire::test(Login::class)
            ->fillForm([
                'account' => 'invalid@example.com',
                'password' => 'incorrect-password',
                'remember' => false,
            ])
            ->call('authenticate')
            ->assertHasErrors(['data.account']);

        $this->assertGuest();
    }

    public function test_password_reset_or_deletion_invalidates_an_ordinary_admin_session(): void
    {
        $admin = User::query()->create([
            'name' => '会话管理员',
            'email' => 'session@example.com',
            'password' => 'initial-password',
        ]);
        $admin->assignRole('admin');
        $oldPasswordHash = $admin->password;

        $admin->forceFill(['password' => 'reset-password'])->save();

        $this->withSession(['password_hash_web' => $oldPasswordHash])
            ->actingAs($admin)
            ->get('/admin')
            ->assertRedirect('/admin/login');

        $admin->delete();

        $this->withSession([
            auth()->guard('web')->getName() => $admin->getKey(),
        ])->get('/admin')->assertRedirect('/admin/login');
    }
}
