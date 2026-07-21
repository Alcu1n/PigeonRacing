<?php

// [IN]: Existing administrator users and permission tables / 既有管理员用户与权限表
// [OUT]: Phone login support, default roles, permissions, and initial super administrator / 手机号登录支持、默认角色权限与初始超级管理员
// [POS]: Backend administrator permission bootstrap migration / 后台管理员权限初始化迁移
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
            $table->string('phone', 32)->nullable()->unique()->after('email');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (AdminPermissions::all() as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('super-admin', 'web');

        User::query()
            ->where('email', 'admin@example.com')
            ->each(function (User $user): void {
                $user->syncRoles(['super-admin']);
            });
    }

    public function down(): void
    {
        DB::table('users')
            ->whereNull('email')
            ->orderBy('id')
            ->each(function (object $user): void {
                DB::table('users')->where('id', $user->id)->update([
                    'email' => "phone-only-{$user->id}@invalid.local",
                ]);
            });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['phone']);
            $table->dropColumn('phone');
            $table->string('email')->nullable(false)->change();
        });
    }
};
