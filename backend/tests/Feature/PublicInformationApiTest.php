<?php
// [IN]: InformationPost records and public API routes / 信息发布记录与公开 API 路由
// [OUT]: Published-only list, detail, filter, ordering, and 404 assertions / 仅发布内容列表、详情、筛选、排序与 404 断言
// [POS]: Backend public information API feature test / 后端公开信息 API 功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Models\InformationPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicInformationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_list_only_returns_published_posts_with_pinned_order(): void
    {
        $normal = $this->postRecord('赛事规程', 'rules-post', InformationPost::CATEGORY_RULES, publishedAt: '2026-07-01 10:00:00');
        $pinned = $this->postRecord('置顶公告', 'pinned-notice', InformationPost::CATEGORY_NOTICE, isPinned: true, publishedAt: '2026-06-30 10:00:00');
        $draft = $this->postRecord('草稿', 'draft-post', InformationPost::CATEGORY_NOTICE, status: InformationPost::STATUS_DRAFT);

        $this->getJson('/api/public/information')
            ->assertOk()
            ->assertJsonPath('items.0.id', $pinned->id)
            ->assertJsonPath('items.1.id', $normal->id)
            ->assertJsonMissing(['id' => $draft->id]);
    }

    public function test_public_list_can_filter_by_category(): void
    {
        $rules = $this->postRecord('赛事规程', 'rules-post', InformationPost::CATEGORY_RULES);
        $this->postRecord('成绩发布', 'results-post', InformationPost::CATEGORY_RESULTS);

        $this->getJson('/api/public/information?category=rules')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $rules->id);
    }

    public function test_public_detail_only_reads_published_slug(): void
    {
        $published = $this->postRecord('通知公告', 'notice-post', InformationPost::CATEGORY_NOTICE);
        $draft = $this->postRecord('草稿', 'draft-post', InformationPost::CATEGORY_NOTICE, status: InformationPost::STATUS_DRAFT);

        $this->getJson('/api/public/information/'.$published->slug)
            ->assertOk()
            ->assertJsonPath('post.content_html', '<p>公开正文</p>');

        $this->getJson('/api/public/information/'.$draft->slug)
            ->assertNotFound();
    }

    private function postRecord(
        string $title,
        string $slug,
        string $category,
        string $status = InformationPost::STATUS_PUBLISHED,
        bool $isPinned = false,
        string $publishedAt = '2026-07-01 09:00:00',
    ): InformationPost {
        return InformationPost::query()->create([
            'title' => $title,
            'slug' => $slug,
            'category' => $category,
            'summary' => '摘要',
            'content_html' => '<p>公开正文</p>',
            'status' => $status,
            'is_pinned' => $isPinned,
            'published_at' => $status === InformationPost::STATUS_PUBLISHED ? $publishedAt : null,
        ]);
    }
}
