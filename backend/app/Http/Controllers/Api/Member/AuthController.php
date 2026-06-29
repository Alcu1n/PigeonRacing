<?php
// [IN]: Member login and logout API requests / 会员登录与退出 API 请求
// [OUT]: Forced account-switch login, logout, and password-policy member responses / 强制切换账号登录、退出与密码策略会员响应
// [POS]: Backend member authentication controller / 后端会员鉴权控制器
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Http\Controllers\Api\Member;

use App\Http\Requests\Member\LoginRequest;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $this->clearMemberSession($request);

        if (! Auth::guard('member')->attempt(['phone' => $credentials['phone'], 'password' => $credentials['password'], 'status' => 'enabled'])) {
            $request->session()->regenerateToken();

            return response()->json(['error_code' => 'invalid_credentials', 'message' => '手机号或密码错误。'], 422);
        }

        $request->session()->regenerate();

        /** @var Member $member */
        $member = Auth::guard('member')->user();
        $member->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'member' => [
                'id' => $member->id,
                'phone' => $member->phone,
                'loft_number' => $member->loft_number,
                'participant_name' => $member->participant_name,
                'must_change_password' => $member->must_change_password,
            ],
        ]);
    }

    public function logout(): JsonResponse
    {
        Auth::guard('member')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }

    private function clearMemberSession(Request $request): void
    {
        Auth::guard('member')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
