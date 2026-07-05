<?php
// [IN]: Laravel schema builder / Laravel 数据库结构构建器
// [OUT]: Public information publishing table / 公开信息发布表
// [POS]: Backend information publishing migration / 后端信息发布迁移
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('information_posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 160);
            $table->string('slug', 180)->unique();
            $table->string('category', 32)->index();
            $table->string('summary', 240)->nullable();
            $table->longText('content_html');
            $table->string('status', 32)->default('draft')->index();
            $table->boolean('is_pinned')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();

            $table->index(['status', 'category', 'is_pinned', 'published_at'], 'information_posts_public_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('information_posts');
    }
};
