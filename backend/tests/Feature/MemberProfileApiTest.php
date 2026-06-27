<?php
// [IN]: Member profile API routes, member session, and password hashes / 会员档案 API 路由、会员会话与密码哈希
// [OUT]: Profile and password-change API assertions / 档案与改密 API 断言
// [POS]: Backend member profile feature test / 后端会员档案功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Pigeon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_profile_include_password_change_state_and_pigeons(): void
    {
        $member = Member::query()->create([
            'phone' => '13800000000',
            'password' => 'password',
            'loft_number' => 'A001',
            'participant_name' => '张三鸽舍',
            'status' => 'enabled',
            'must_change_password' => true,
        ]);
        Pigeon::query()->create([
            'member_id' => $member->id,
            'loft_number' => 'A001',
            'participant_name' => '张三鸽舍',
            'ring_number' => '2026-13-000001',
            'status' => 'normal',
        ]);

        $this->postJson('/api/member/login', ['phone' => '13800000000', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('member.must_change_password', true);

        $this->actingAs($member, 'member')
            ->getJson('/api/member/profile')
            ->assertOk()
            ->assertJsonPath('member.must_change_password', true)
            ->assertJsonPath('pigeons.0.ring_number', '2026-13-000001');
    }

    public function test_password_change_requires_current_password_and_clears_required_flag(): void
    {
        $member = Member::query()->create([
            'phone' => '13800000001',
            'password' => 'password',
            'loft_number' => 'A002',
            'participant_name' => '李四鸽舍',
            'status' => 'enabled',
            'must_change_password' => true,
        ]);

        $this->actingAs($member, 'member')
            ->postJson('/api/member/password', [
                'current_password' => 'wrong',
                'password' => 'newpass',
                'password_confirmation' => 'newpass',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', '当前密码错误。');

        $this->actingAs($member, 'member')
            ->postJson('/api/member/password', [
                'current_password' => 'password',
                'password' => 'newpass',
                'password_confirmation' => 'newpass',
            ])
            ->assertOk()
            ->assertJsonPath('member.must_change_password', false);

        $member->refresh();
        $this->assertFalse($member->must_change_password);
        $this->assertTrue(Hash::check('newpass', $member->password));
    }
}
