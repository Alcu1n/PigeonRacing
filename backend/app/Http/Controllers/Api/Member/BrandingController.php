<?php
// [IN]: Public member branding request / 公开会员品牌请求
// [OUT]: Login logo URL response / 登录 Logo 地址响应
// [POS]: Backend member branding controller / 后端会员品牌控制器
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Http\Controllers\Api\Member;

use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class BrandingController extends Controller
{
    public function show(): JsonResponse
    {
        $path = AppSetting::getValue(AppSetting::BRAND_LOGO_PATH);

        return response()->json([
            'logo_url' => $path ? '/storage/'.ltrim($path, '/') : null,
        ]);
    }
}
