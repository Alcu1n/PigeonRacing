<?php
// [IN]: Laravel schema builder / Laravel 数据库结构构建器
// [OUT]: Member login and identity table / 会员登录与身份表
// [POS]: Backend member migration / 后端会员迁移
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table): void {
            $table->id();
            $table->string('phone')->unique();
            $table->string('password');
            $table->string('loft_number')->index();
            $table->string('participant_name');
            $table->string('status')->default('enabled')->index();
            $table->text('remark')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
