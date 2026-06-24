<?php
// [IN]: Authenticated member registration API requests / 已鉴权会员报名 API 请求
// [OUT]: Registration submit and detail responses / 报名提交与详情响应
// [POS]: Backend member registration controller / 后端会员报名控制器
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Http\Controllers\Api\Member;

use App\Http\Requests\Member\SubmitRegistrationRequest;
use App\Models\Member;
use App\Models\Race;
use App\Models\Registration;
use App\Services\RaceCacheService;
use App\Services\RegistrationRuleException;
use App\Services\RegistrationSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class RegistrationController extends Controller
{
    public function store(Race $race, SubmitRegistrationRequest $request, RegistrationSubmissionService $service, RaceCacheService $cache): JsonResponse
    {
        /** @var Member $member */
        $member = auth('member')->user();
        $payload = $request->validated();

        try {
            $registration = $service->submit(
                $member,
                $race,
                (int) $payload['config_version'],
                $payload['idempotency_key'],
                $payload['entries']
            );
        } catch (RegistrationRuleException $exception) {
            return response()->json([
                'error_code' => $exception->errorCode,
                'message' => $exception->getMessage(),
            ], $exception->httpStatus);
        }

        return response()->json($cache->serializeRegistration($registration));
    }

    public function show(Registration $registration, RaceCacheService $cache): JsonResponse
    {
        /** @var Member $member */
        $member = auth('member')->user();

        if ($registration->member_id !== $member->id) {
            return response()->json(['error_code' => 'registration_not_found', 'message' => '报名记录不存在。'], 404);
        }

        return response()->json($cache->serializeRegistration($registration->load('entries.pigeons')));
    }
}
