<?php
// [IN]: Public information API requests / 公开信息发布 API 请求
// [OUT]: Published information post list and detail JSON / 已发布信息列表与详情 JSON
// [POS]: Backend public information API controller / 后端公开信息 API 控制器
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Http\Controllers\Api\Public;

use App\Models\InformationPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class InformationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $category = $request->query('category');

        if ($category !== null && ! InformationPost::isValidCategory((string) $category)) {
            return response()->json(['message' => '分类不存在'], 422);
        }

        $posts = InformationPost::query()
            ->published()
            ->when($category, fn ($query) => $query->where('category', $category))
            ->publicOrder()
            ->get();

        return response()->json([
            'items' => $posts->map(fn (InformationPost $post): array => $this->summary($post))->values(),
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $post = InformationPost::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json([
            'post' => [
                ...$this->summary($post),
                'content_html' => $post->content_html,
            ],
        ]);
    }

    private function summary(InformationPost $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'category' => $post->category,
            'summary' => $post->summary,
            'is_pinned' => $post->is_pinned,
            'published_at' => $post->published_at?->toDateTimeString(),
        ];
    }
}
