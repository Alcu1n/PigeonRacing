<?php
// [IN]: Member API HTTP response pipeline / 会员 API HTTP 响应管线
// [OUT]: No-store cache headers for session-sensitive JSON / 会话敏感 JSON 的 no-store 缓存头
// [POS]: Backend member API cache-control middleware / 后端会员 API 缓存控制中间件
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NoStoreMemberApiResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
