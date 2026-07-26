<?php

// [IN]: Member race list API requests with varied registration windows / 不同报名窗口的会员赛事列表 API 请求
// [OUT]: Registration state assertions for pending, open, and ended races / 未开始、报名中与已结束赛事的报名状态断言
// [POS]: Backend member race list feature test / 后端会员赛事列表功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Enums\RaceStatus;
use App\Models\Member;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberRaceListApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_race_list_reports_pending_open_and_ended_registration_states(): void
    {
        $member = $this->member('A001');
        $pending = $this->race([
            'name' => '未来赛事',
            'registration_start_at' => now()->addDay(),
            'registration_end_at' => now()->addDays(3),
        ]);
        $open = $this->race([
            'name' => '报名中赛事',
            'registration_start_at' => now()->subDay(),
            'registration_end_at' => now()->addDay(),
        ]);
        $ended = $this->race([
            'name' => '已结束赛事',
            'registration_start_at' => now()->subDays(10),
            'registration_end_at' => now()->subDay(),
        ]);

        $this->actingAs($member, 'member')
            ->getJson('/api/member/races')
            ->assertOk()
            ->assertJsonFragment(['id' => $pending->id, 'registration_state' => 'pending'])
            ->assertJsonFragment(['id' => $open->id, 'registration_state' => 'open'])
            ->assertJsonFragment(['id' => $ended->id, 'registration_state' => 'ended']);
    }

    private function member(string $loftNumber): Member
    {
        return Member::query()->create([
            'phone' => null,
            'password' => 'password',
            'loft_number' => $loftNumber,
            'participant_name' => $loftNumber.'鸽舍',
            'status' => 'enabled',
        ]);
    }

    private function race(array $overrides = []): Race
    {
        return Race::query()->create(array_merge([
            'name' => '测试赛事',
            'registration_start_at' => now()->subDays(10),
            'registration_end_at' => now()->subDay(),
            'status' => RaceStatus::Published,
            'is_visible' => true,
            'registration_details_scope' => Race::DETAILS_SCOPE_CONFIRMED_ONLY,
        ], $overrides));
    }
}
