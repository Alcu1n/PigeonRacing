<?php
// [IN]: Authenticated member registration API requests and history queries / 已鉴权会员报名 API 请求与历史查询
// [OUT]: Registration submit, history list, and detail responses / 报名提交、历史列表与详情响应
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
    public function index(): JsonResponse
    {
        /** @var Member $member */
        $member = auth('member')->user();

        $registrations = $member->registrations()
            ->with(['race', 'entries', 'progressiveStageEntries'])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get()
            ->unique('race_id')
            ->map(fn (Registration $registration): array => [
                'registration_id' => $registration->id,
                'race_id' => $registration->race_id,
                'race_name' => $registration->race?->name ?? '未知赛事',
                'registration_no' => $registration->registration_no,
                'status' => $registration->status->value,
                'total_amount_cent' => $registration->total_amount_cent,
                'submitted_at' => optional($registration->submitted_at)->toDateTimeString(),
                'single_count' => $registration->entries
                    ->where('group_size_snapshot', 1)
                    ->count(),
                'multi_group_count' => $registration->entries
                    ->where('group_size_snapshot', '>', 1)
                    ->count(),
                'progressive_count' => $registration->progressiveStageEntries
                    ->groupBy(fn ($entry): string => $entry->race_project_id.':'.($entry->group_key ?: $entry->pigeon_id))
                    ->count(),
            ])
            ->values();

        return response()->json($registrations);
    }

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
                $payload['entries'] ?? [],
                $payload['progressive_entries'] ?? [],
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

        $registration->load(['race', 'entries.pigeons', 'progressiveStageEntries.category']);

        return response()->json([
            ...$cache->serializeRegistration($registration),
            'race_id' => $registration->race_id,
            'race_name' => $registration->race?->name ?? '未知赛事',
        ]);
    }
}
