<?php
// [IN]: Authenticated member profile and password API requests / 已鉴权会员档案与改密 API 请求
// [OUT]: Member profile, pigeon list, and password-change responses / 会员档案、足环列表与改密响应
// [POS]: Backend member profile controller / 后端会员档案控制器
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Http\Controllers\Api\Member;

use App\Http\Requests\Member\UpdatePasswordRequest;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ProfileController extends Controller
{
    public function show(): JsonResponse
    {
        /** @var Member $member */
        $member = auth('member')->user();

        return response()->json($this->profilePayload($member));
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        /** @var Member $member */
        $member = auth('member')->user();
        $payload = $request->validated();

        $member->forceFill([
            'password' => $payload['password'],
            'must_change_password' => false,
        ])->save();

        return response()->json($this->profilePayload($member->refresh()));
    }

    private function profilePayload(Member $member): array
    {
        return [
            'member' => [
                'id' => $member->id,
                'phone' => $member->phone,
                'loft_number' => $member->loft_number,
                'participant_name' => $member->participant_name,
                'must_change_password' => $member->must_change_password,
            ],
            'pigeons' => $member->pigeons()
                ->orderBy('ring_number')
                ->get(['id', 'ring_number'])
                ->values()
                ->all(),
        ];
    }
}
