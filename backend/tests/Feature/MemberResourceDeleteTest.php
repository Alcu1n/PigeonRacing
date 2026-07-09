<?php
// [IN]: Member resource deletion helper, member rows, pigeons, race cache keys / 会员资源删除助手、会员行、足环与赛事缓存键
// [OUT]: Selected member deletion, cascaded pigeons, and cache invalidation assertions / 所选会员删除、级联足环与缓存失效断言
// [POS]: Backend member resource deletion feature test / 后端会员资源删除功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Enums\RaceStatus;
use App\Filament\Resources\MemberResource;
use App\Models\Member;
use App\Models\Pigeon;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MemberResourceDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_members_removes_selected_members_pigeons_and_cached_bootstrap(): void
    {
        $member = $this->member('13900000010', 'A010');
        $remaining = $this->member('13900000011', 'A011');
        $race = Race::query()->create([
            'name' => '测试赛事',
            'registration_start_at' => now()->subDay(),
            'registration_end_at' => now()->addDay(),
            'status' => RaceStatus::Published,
            'is_visible' => true,
            'config_version' => 1,
        ]);

        Pigeon::query()->create([
            'member_id' => $member->id,
            'loft_number' => $member->loft_number,
            'participant_name' => $member->participant_name,
            'ring_number' => '2026-13-900010',
            'status' => 'normal',
        ]);

        Cache::put("member:{$member->id}:pigeons", ['stale'], 600);
        Cache::put("race:{$race->id}:member:{$member->id}:bootstrap", ['stale'], 600);
        Cache::put("race:{$race->id}:version:1:member:{$member->id}:bootstrap", ['stale'], 600);

        $deleted = MemberResource::deleteMembers(collect([$member]));

        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing('members', ['id' => $member->id]);
        $this->assertDatabaseMissing('pigeons', ['member_id' => $member->id]);
        $this->assertDatabaseHas('members', ['id' => $remaining->id]);
        $this->assertFalse(Cache::has("member:{$member->id}:pigeons"));
        $this->assertFalse(Cache::has("race:{$race->id}:member:{$member->id}:bootstrap"));
        $this->assertFalse(Cache::has("race:{$race->id}:version:1:member:{$member->id}:bootstrap"));
    }

    private function member(string $phone, string $loftNumber): Member
    {
        return Member::query()->create([
            'phone' => $phone,
            'password' => 'password',
            'loft_number' => $loftNumber,
            'participant_name' => '测试鸽舍',
            'status' => 'enabled',
        ]);
    }
}
