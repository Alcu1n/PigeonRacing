<?php
// [IN]: Pigeon model persistence and cache store / 足环模型持久化与缓存存储
// [OUT]: Cache invalidation assertions / 缓存失效断言
// [POS]: Backend pigeon cache feature test / 后端足环缓存功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Enums\RaceStatus;
use App\Models\Member;
use App\Models\Pigeon;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PigeonCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_pigeon_invalidates_member_pigeons_and_bootstrap_cache(): void
    {
        $member = Member::query()->create([
            'phone' => '13900000000',
            'password' => 'password',
            'loft_number' => 'T001',
            'participant_name' => '测试鸽舍',
            'status' => 'enabled',
        ]);

        $race = Race::query()->create([
            'name' => '测试赛事',
            'registration_start_at' => now()->subDay(),
            'registration_end_at' => now()->addDay(),
            'status' => RaceStatus::Published,
            'is_visible' => true,
        ]);

        Cache::put("member:{$member->id}:pigeons", ['stale'], 600);
        Cache::put("race:{$race->id}:member:{$member->id}:bootstrap", ['stale'], 600);

        Pigeon::query()->create([
            'member_id' => $member->id,
            'loft_number' => $member->loft_number,
            'participant_name' => $member->participant_name,
            'ring_number' => '2025-13-0530018',
            'status' => 'normal',
        ]);

        $this->assertFalse(Cache::has("member:{$member->id}:pigeons"));
        $this->assertFalse(Cache::has("race:{$race->id}:member:{$member->id}:bootstrap"));
    }
}
