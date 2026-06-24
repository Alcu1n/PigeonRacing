<?php
// [IN]: Laravel schema builder / Laravel 数据库结构构建器
// [OUT]: Race, project, pigeon, registration, import, and audit tables / 赛事、项目、足环、报名、导入与审计表
// [POS]: Backend core business migration / 后端核心业务迁移
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('file_name');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('processing')->index();
            $table->string('error_report_path')->nullable();
            $table->timestamps();
        });

        Schema::create('pigeons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('loft_number')->index();
            $table->string('participant_name');
            $table->string('ring_number')->unique();
            $table->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->string('status')->default('normal')->index();
            $table->timestamps();
        });

        Schema::create('races', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->dateTime('registration_start_at')->index();
            $table->dateTime('registration_end_at')->index();
            $table->string('status')->default('draft')->index();
            $table->unsignedInteger('config_version')->default(1);
            $table->boolean('allow_member_edit')->default(true);
            $table->boolean('require_admin_confirm')->default(true);
            $table->boolean('is_visible')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('race_projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('race_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('group_size');
            $table->unsignedInteger('price_cent');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('allow_repeat_pigeon_in_project')->default(false);
            $table->unsignedInteger('max_entries_per_member')->nullable();
            $table->unsignedInteger('max_usage_per_pigeon')->nullable();
            $table->timestamps();
            $table->index(['race_id', 'is_enabled']);
        });

        Schema::create('registrations', function (Blueprint $table): void {
            $table->id();
            $table->string('registration_no')->unique();
            $table->foreignId('race_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('total_amount_cent')->default(0);
            $table->string('status')->index();
            $table->uuid('idempotency_key');
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remark')->nullable();
            $table->timestamps();
            $table->unique(['race_id', 'member_id']);
            $table->unique(['member_id', 'race_id', 'idempotency_key'], 'registrations_member_race_idem_unique');
        });

        Schema::create('registration_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('race_project_id')->constrained('race_projects')->restrictOnDelete();
            $table->string('project_name_snapshot');
            $table->unsignedSmallInteger('group_size_snapshot');
            $table->unsignedInteger('price_cent_snapshot');
            $table->unsignedInteger('group_index');
            $table->timestamp('created_at');
            $table->index('race_project_id');
        });

        Schema::create('registration_entry_pigeons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('registration_entry_id')->constrained('registration_entries')->cascadeOnDelete();
            $table->foreignId('pigeon_id')->constrained()->restrictOnDelete();
            $table->string('ring_number_snapshot')->index();
            $table->unsignedInteger('sort_order');
            $table->timestamp('created_at');
            $table->index('pigeon_id');
        });

        Schema::create('admin_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('detail')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->timestamp('created_at');
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_logs');
        Schema::dropIfExists('registration_entry_pigeons');
        Schema::dropIfExists('registration_entries');
        Schema::dropIfExists('registrations');
        Schema::dropIfExists('race_projects');
        Schema::dropIfExists('races');
        Schema::dropIfExists('pigeons');
        Schema::dropIfExists('import_batches');
    }
};
