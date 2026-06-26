<?php
// [IN]: Pigeon/project persistence, race cache service, and cache store / 足环和项目持久化、赛事缓存服务与缓存存储
// [OUT]: Cache invalidation and versioned bootstrap assertions / 缓存失效与带版本初始化断言
// [POS]: Backend pigeon cache feature test / 后端足环缓存功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Enums\RaceStatus;
use App\Models\Member;
use App\Models\Pigeon;
use App\Models\Race;
use App\Models\RaceProject;
use App\Services\RaceCacheService;
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
        Cache::put("race:{$race->id}:version:1:member:{$member->id}:bootstrap", ['stale'], 600);

        Pigeon::query()->create([
            'member_id' => $member->id,
            'loft_number' => $member->loft_number,
            'participant_name' => $member->participant_name,
            'ring_number' => '2025-13-0530018',
            'status' => 'normal',
        ]);

        $this->assertFalse(Cache::has("member:{$member->id}:pigeons"));
        $this->assertFalse(Cache::has("race:{$race->id}:member:{$member->id}:bootstrap"));
        $this->assertFalse(Cache::has("race:{$race->id}:version:1:member:{$member->id}:bootstrap"));
    }

    public function test_saving_race_project_invalidates_race_config_and_bootstrap_cache(): void
    {
        $member = Member::query()->create([
            'phone' => '13900000004',
            'password' => 'password',
            'loft_number' => 'T002',
            'participant_name' => '测试鸽舍',
            'status' => 'enabled',
        ]);

        $race = Race::query()->create([
            'name' => '测试赛事',
            'registration_start_at' => now()->subDay(),
            'registration_end_at' => now()->addDay(),
            'status' => RaceStatus::Published,
            'is_visible' => true,
            'config_version' => 1,
        ]);

        Cache::put("race:{$race->id}:config", ['stale'], 600);
        Cache::put("race:{$race->id}:member:{$member->id}:bootstrap", ['stale'], 600);
        Cache::put("race:{$race->id}:version:1:member:{$member->id}:bootstrap", ['stale'], 600);

        RaceProject::query()->create([
            'race_id' => $race->id,
            'name' => '双羽组',
            'group_size' => 2,
            'price_cent' => 20000,
            'allow_repeat_pigeon_in_project' => true,
        ]);

        $this->assertSame(2, $race->fresh()->config_version);
        $this->assertFalse(Cache::has("race:{$race->id}:config"));
        $this->assertFalse(Cache::has("race:{$race->id}:member:{$member->id}:bootstrap"));
        $this->assertFalse(Cache::has("race:{$race->id}:version:2:member:{$member->id}:bootstrap"));
    }

    public function test_project_change_uses_new_bootstrap_version_key(): void
    {
        $member = Member::query()->create([
            'phone' => '13900000005',
            'password' => 'password',
            'loft_number' => 'T003',
            'participant_name' => '测试鸽舍',
            'status' => 'enabled',
        ]);

        $race = Race::query()->create([
            'name' => '测试赛事',
            'registration_start_at' => now()->subDay(),
            'registration_end_at' => now()->addDay(),
            'status' => RaceStatus::Published,
            'is_visible' => true,
            'config_version' => 1,
        ]);

        $project = RaceProject::query()->create([
            'race_id' => $race->id,
            'name' => '单羽 50',
            'group_size' => 1,
            'price_cent' => 5000,
            'is_enabled' => true,
        ]);

        $cache = app(RaceCacheService::class);
        $first = $cache->bootstrap($race->fresh(), $member);
        $this->assertSame('单羽 50', $first['projects'][0]['name']);

        $project->forceFill(['name' => '单羽 80', 'price_cent' => 8000])->save();
        Cache::put("race:{$race->id}:config", collect([['name' => '旧配置']]), 600);
        Cache::put("race:{$race->id}:version:2:config", collect([['name' => '旧版本配置']]), 600);
        $second = $cache->bootstrap($race->fresh(), $member);

        $this->assertSame(3, $race->fresh()->config_version);
        $this->assertSame('单羽 80', $second['projects'][0]['name']);
        $this->assertSame(8000, $second['projects'][0]['price_cent']);
    }
}
