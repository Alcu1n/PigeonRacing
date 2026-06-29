<?php
// [IN]: Member login API, session guard, and member profile API / 会员登录 API、会话 guard 与会员档案 API
// [OUT]: Account-switch and failed-login session isolation assertions / 账号切换与失败登录会话隔离断言
// [POS]: Backend member session switching feature test / 后端会员会话切换功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberSessionSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_logged_in_member_can_switch_to_another_member_by_login(): void
    {
        $first = $this->member('13800000001', 'A001');
        $second = $this->member('13800000002', 'B001');

        $this->postJson('/api/member/login', ['phone' => $first->phone, 'password' => 'password'])->assertOk();

        $switchResponse = $this->postJson('/api/member/login', ['phone' => $second->phone, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('member.id', $second->id);
        $this->assertStringContainsString('no-store', (string) $switchResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', (string) $switchResponse->headers->get('Cache-Control'));

        $this->getJson('/api/member/profile')
            ->assertOk()
            ->assertJsonPath('member.id', $second->id);
    }

    public function test_failed_switch_login_does_not_keep_previous_member_session(): void
    {
        $first = $this->member('13800000003', 'A003');
        $second = $this->member('13800000004', 'B004');

        $this->postJson('/api/member/login', ['phone' => $first->phone, 'password' => 'password'])->assertOk();

        $failedResponse = $this->postJson('/api/member/login', ['phone' => $second->phone, 'password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'invalid_credentials');
        $this->assertStringContainsString('no-store', (string) $failedResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', (string) $failedResponse->headers->get('Cache-Control'));

        $this->getJson('/api/member/profile')->assertUnauthorized();
    }

    private function member(string $phone, string $loftNumber): Member
    {
        return Member::query()->create([
            'phone' => $phone,
            'password' => 'password',
            'loft_number' => $loftNumber,
            'participant_name' => $loftNumber.'鸽舍',
            'status' => 'enabled',
        ]);
    }
}
