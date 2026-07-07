<?php
// [IN]: Existing progressive stage entry rows / 既有递进阶段报名结果行
// [OUT]: Group-aware progressive stage entry schema / 支持组概念的递进阶段报名结果结构
// [POS]: Backend progressive multi-pigeon stage migration / 后端递进多羽阶段迁移
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('progressive_stage_entries', function (Blueprint $table): void {
            $table->index('registration_category_id', 'progressive_stage_category_safe_index');
            $table->index('race_project_id', 'progressive_stage_project_safe_index');
            $table->index('member_id', 'progressive_stage_member_safe_index');
            $table->index('pigeon_id', 'progressive_stage_pigeon_safe_index');
        });

        Schema::table('progressive_stage_entries', function (Blueprint $table): void {
            $table->dropUnique('progressive_stage_member_pigeon_unique');
            $table->string('group_key', 64)->nullable()->after('member_id');
            $table->unsignedInteger('group_index')->default(1)->after('group_key');
            $table->unsignedSmallInteger('group_size_snapshot')->default(1)->after('group_index');
            $table->unsignedSmallInteger('pigeon_sort_order')->default(1)->after('pigeon_id');
            $table->index(['registration_category_id', 'race_project_id', 'member_id', 'group_key'], 'progressive_stage_group_index');
        });

        DB::table('progressive_stage_entries')
            ->orderBy('id')
            ->get(['id', 'pigeon_id'])
            ->each(function (object $entry): void {
                DB::table('progressive_stage_entries')
                    ->where('id', $entry->id)
                    ->update([
                        'group_key' => (string) $entry->pigeon_id,
                        'group_index' => 1,
                        'group_size_snapshot' => 1,
                        'pigeon_sort_order' => 1,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('progressive_stage_entries', function (Blueprint $table): void {
            $table->dropIndex('progressive_stage_group_index');
            $table->dropColumn(['group_key', 'group_index', 'group_size_snapshot', 'pigeon_sort_order']);
            $table->unique(['registration_category_id', 'race_project_id', 'member_id', 'pigeon_id'], 'progressive_stage_member_pigeon_unique');
        });

        Schema::table('progressive_stage_entries', function (Blueprint $table): void {
            $table->dropIndex('progressive_stage_category_safe_index');
            $table->dropIndex('progressive_stage_project_safe_index');
            $table->dropIndex('progressive_stage_member_safe_index');
            $table->dropIndex('progressive_stage_pigeon_safe_index');
        });
    }
};
