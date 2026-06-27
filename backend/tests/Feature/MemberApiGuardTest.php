<?php
// [IN]: Member API routes plus admin and member sessions / 会员 API 路由及后台管理员、会员会话
// [OUT]: Guard isolation assertions for member-only API access / 会员专属 API 访问的 guard 隔离断言
// [POS]: Backend member API guard feature test / 后端会员 API guard 功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Enums\RaceStatus;
use App\Models\Member;
use App\Models\Race;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberApiGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_session_cannot_enter_member_bootstrap_api(): void
    {
        $race = $this->race();
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $this->actingAs($admin, 'web')
            ->getJson("/api/member/races/{$race->id}/bootstrap")
            ->assertUnauthorized();
    }

    public function test_member_session_can_enter_member_bootstrap_api(): void
    {
        $race = $this->race();
        $member = Member::query()->create([
            'phone' => '13800000000',
            'password' => 'password',
            'loft_number' => 'A001',
            'participant_name' => '张三鸽舍',
            'status' => 'enabled',
        ]);

        $this->actingAs($member, 'member')
            ->getJson("/api/member/races/{$race->id}/bootstrap")
            ->assertOk()
            ->assertJsonPath('member.id', $member->id);
    }

    private function race(): Race
    {
        return Race::query()->create([
            'name' => '测试赛事',
            'registration_start_at' => now()->subDay(),
            'registration_end_at' => now()->addDay(),
            'status' => RaceStatus::Published,
            'is_visible' => true,
        ]);
    }
}
