<?php
// [IN]: Existing members table schema / 既有会员表结构
// [OUT]: Nullable login credentials and unique loft numbers / 可空登录凭据与唯一棚号
// [POS]: Backend member import compatibility migration / 后端会员导入兼容迁移
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->string('phone')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->dropIndex(['loft_number']);
            $table->unique('loft_number');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->dropUnique(['loft_number']);
            $table->index('loft_number');
            $table->string('phone')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
