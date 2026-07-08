<?php

// [IN]: Existing pigeons, race projects, and import batches / 既有足环、报名项目与导入批次
// [OUT]: Named pigeon libraries with per-library ring uniqueness and project links / 命名足环库、库内足环唯一约束与项目关联
// [POS]: Backend pigeon library migration / 后端足环库迁移
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pigeon_libraries', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_enabled')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $defaultLibraryId = DB::table('pigeon_libraries')->insertGetId([
            'name' => '默认足环库',
            'is_enabled' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('pigeons', function (Blueprint $table): void {
            $table->foreignId('pigeon_library_id')
                ->nullable()
                ->after('id')
                ->constrained('pigeon_libraries')
                ->restrictOnDelete();
        });

        DB::table('pigeons')->update(['pigeon_library_id' => $defaultLibraryId]);

        Schema::table('pigeons', function (Blueprint $table): void {
            $table->dropUnique('pigeons_ring_number_unique');
            $table->unique(['pigeon_library_id', 'ring_number'], 'pigeons_library_ring_unique');
            $table->index(['pigeon_library_id', 'member_id', 'status'], 'pigeons_library_member_status_index');
        });

        Schema::table('race_projects', function (Blueprint $table): void {
            $table->foreignId('pigeon_library_id')
                ->nullable()
                ->after('race_id')
                ->constrained('pigeon_libraries')
                ->nullOnDelete();
        });

        DB::table('race_projects')->update(['pigeon_library_id' => $defaultLibraryId]);

        Schema::table('import_batches', function (Blueprint $table): void {
            $table->foreignId('pigeon_library_id')
                ->nullable()
                ->after('id')
                ->constrained('pigeon_libraries')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pigeon_library_id');
        });

        Schema::table('race_projects', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pigeon_library_id');
        });

        Schema::table('pigeons', function (Blueprint $table): void {
            $table->dropIndex('pigeons_library_member_status_index');
            $table->dropUnique('pigeons_library_ring_unique');
            $table->unique('ring_number', 'pigeons_ring_number_unique');
            $table->dropConstrainedForeignId('pigeon_library_id');
        });

        Schema::dropIfExists('pigeon_libraries');
    }
};
