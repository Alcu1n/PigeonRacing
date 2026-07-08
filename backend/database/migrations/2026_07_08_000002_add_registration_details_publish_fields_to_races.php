<?php
// [IN]: Existing races table / 已存在赛事表
// [OUT]: Registration detail publication flags and scope / 报名明细发布标记与范围
// [POS]: Backend race detail publication migration / 后端赛事明细发布迁移
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('races', function (Blueprint $table): void {
            $table->timestamp('registration_details_published_at')->nullable()->after('is_visible')->index();
            $table->string('registration_details_scope')->default('confirmed_only')->after('registration_details_published_at');
        });
    }

    public function down(): void
    {
        Schema::table('races', function (Blueprint $table): void {
            $table->dropColumn(['registration_details_published_at', 'registration_details_scope']);
        });
    }
};
