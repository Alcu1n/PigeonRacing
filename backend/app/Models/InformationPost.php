<?php
// [IN]: Information publishing rows / 信息发布行
// [OUT]: Category/status labels, public scopes, and generated slugs / 分类状态标签、公开查询作用域与自动 slug
// [POS]: Backend information post aggregate / 后端信息发布聚合
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class InformationPost extends Model
{
    public const CATEGORY_RULES = 'rules';
    public const CATEGORY_RESULTS = 'results';
    public const CATEGORY_NOTICE = 'notice';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'title',
        'slug',
        'category',
        'summary',
        'content_html',
        'status',
        'is_pinned',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (InformationPost $post): void {
            if (blank($post->slug)) {
                $post->slug = self::uniqueSlug($post->title, $post->id);
            }

            if ($post->status === self::STATUS_PUBLISHED && blank($post->published_at)) {
                $post->published_at = now();
            }
        });
    }

    public static function categoryOptions(): array
    {
        return [
            self::CATEGORY_RULES => '赛事规程',
            self::CATEGORY_RESULTS => '成绩发布',
            self::CATEGORY_NOTICE => '通知公告',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => '草稿',
            self::STATUS_PUBLISHED => '发布',
        ];
    }

    public static function categoryLabel(?string $category): string
    {
        return self::categoryOptions()[$category] ?? (string) $category;
    }

    public static function statusLabel(?string $status): string
    {
        return self::statusOptions()[$status] ?? (string) $status;
    }

    public static function isValidCategory(?string $category): bool
    {
        return array_key_exists((string) $category, self::categoryOptions());
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopePublicOrder(Builder $query): Builder
    {
        return $query
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    private static function uniqueSlug(string $title, ?int $ignoreId): string
    {
        $base = Str::slug($title) ?: 'post-'.now()->format('YmdHis');
        $slug = $base;
        $suffix = 2;

        while (self::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $query): Builder => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
