<?php
// [IN]: Authenticated member race API requests / 已鉴权会员赛事 API 请求
// [OUT]: Visible races and bootstrap data / 可见赛事与初始化数据
// [POS]: Backend member race controller / 后端会员赛事控制器
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Http\Controllers\Api\Member;

use App\Enums\RaceStatus;
use App\Models\Member;
use App\Models\Race;
use App\Services\RaceCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class RaceController extends Controller
{
    public function index(): JsonResponse
    {
        $races = Race::query()
            ->where('is_visible', true)
            ->whereIn('status', [RaceStatus::Published->value, RaceStatus::Closed->value])
            ->orderByDesc('registration_end_at')
            ->get()
            ->map(fn (Race $race): array => [
                'id' => $race->id,
                'name' => $race->name,
                'registration_end_at' => $race->registration_end_at->toDateTimeString(),
                'status' => $race->isOpenForRegistration() ? 'open' : $race->status->value,
            ]);

        return response()->json($races);
    }

    public function bootstrap(Race $race, RaceCacheService $cache): JsonResponse
    {
        /** @var Member $member */
        $member = auth('member')->user();

        if (! $race->is_visible) {
            return response()->json(['error_code' => 'race_not_visible', 'message' => '赛事不可见。'], 404);
        }

        return response()->json($cache->bootstrap($race, $member));
    }
}
