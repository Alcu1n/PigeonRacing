<?php
// [IN]: Existing race projects and registration tables / 既有赛事项目与报名表
// [OUT]: Progressive category, stage project fields, and stage entry tables / 递进类别、阶段项目字段与阶段报名结果表
// [POS]: Backend progressive registration migration / 后端递进阶段报名迁移
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('registration_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('race_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('progressive')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true)->index();
            $table->unsignedBigInteger('current_stage_project_id')->nullable()->index();
            $table->timestamps();
            $table->index(['race_id', 'type', 'is_enabled']);
        });

        Schema::table('race_projects', function (Blueprint $table): void {
            $table->string('project_type')->default('standard')->after('race_id')->index();
            $table->unsignedBigInteger('registration_category_id')->nullable()->after('project_type')->index();
            $table->unsignedInteger('stage_order')->nullable()->after('registration_category_id');
            $table->index(['registration_category_id', 'stage_order'], 'race_projects_category_stage_index');
        });

        Schema::create('progressive_stage_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('registration_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('race_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_category_id')->constrained('registration_categories')->cascadeOnDelete();
            $table->foreignId('race_project_id')->constrained('race_projects')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pigeon_id')->constrained()->restrictOnDelete();
            $table->string('loft_number_snapshot');
            $table->string('participant_name_snapshot');
            $table->string('ring_number_snapshot')->index();
            $table->string('project_name_snapshot');
            $table->unsignedInteger('price_cent_snapshot');
            $table->string('status')->index();
            $table->string('source')->default('member')->index();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['registration_category_id', 'race_project_id', 'member_id', 'pigeon_id'], 'progressive_stage_member_pigeon_unique');
            $table->index(['member_id', 'registration_category_id', 'race_project_id'], 'progressive_stage_member_project_index');
            $table->index(['race_project_id', 'status'], 'progressive_stage_project_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progressive_stage_entries');

        Schema::table('race_projects', function (Blueprint $table): void {
            $table->dropIndex('race_projects_category_stage_index');
            $table->dropIndex(['registration_category_id']);
            $table->dropColumn(['project_type', 'registration_category_id', 'stage_order']);
        });

        Schema::dropIfExists('registration_categories');
    }
};
