<?php

// [IN]: Member profile API routes, member session, and password hashes / 会员档案 API 路由、会员会话与密码哈希
// [OUT]: Profile and password-change API assertions / 档案与改密 API 断言
// [POS]: Backend member profile feature test / 后端会员档案功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Pigeon;
use App\Models\PigeonLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_profile_include_password_change_state_and_pigeons(): void
    {
        $library = PigeonLibrary::default();
        $member = Member::query()->create([
            'phone' => '13800000000',
            'password' => 'password',
            'loft_number' => 'A001',
            'participant_name' => '张三鸽舍',
            'status' => 'enabled',
            'must_change_password' => true,
        ]);
        Pigeon::query()->create([
            'pigeon_library_id' => $library->id,
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
            ->assertJsonPath('pigeons.0.ring_number', '2026-13-000001')
            ->assertJsonPath('pigeon_libraries.0.name', '默认足环库')
            ->assertJsonPath('pigeon_libraries.0.pigeons.0.ring_number', '2026-13-000001');
    }

    public function test_profile_groups_member_pigeons_by_library_and_hides_empty_libraries(): void
    {
        $firstLibrary = PigeonLibrary::query()->create(['name' => '一关库', 'is_enabled' => true, 'sort_order' => 1]);
        $secondLibrary = PigeonLibrary::query()->create(['name' => '二关库', 'is_enabled' => true, 'sort_order' => 2]);
        PigeonLibrary::query()->create(['name' => '空库', 'is_enabled' => true, 'sort_order' => 3]);
        $member = Member::query()->create([
            'phone' => '13800000002',
            'password' => 'password',
            'loft_number' => 'A003',
            'participant_name' => '王五鸽舍',
            'status' => 'enabled',
        ]);
        foreach ([[$firstLibrary, '2026-13-000001'], [$secondLibrary, '2026-13-000002']] as [$library, $ringNumber]) {
            Pigeon::query()->create([
                'pigeon_library_id' => $library->id,
                'member_id' => $member->id,
                'loft_number' => $member->loft_number,
                'participant_name' => $member->participant_name,
                'ring_number' => $ringNumber,
                'status' => 'normal',
            ]);
        }

        $this->actingAs($member, 'member')
            ->getJson('/api/member/profile')
            ->assertOk()
            ->assertJsonCount(2, 'pigeon_libraries')
            ->assertJsonPath('pigeon_libraries.0.name', '一关库')
            ->assertJsonPath('pigeon_libraries.0.pigeon_count', 1)
            ->assertJsonPath('pigeon_libraries.1.name', '二关库')
            ->assertJsonPath('pigeons.0.ring_number', '2026-13-000001')
            ->assertJsonPath('pigeons.1.ring_number', '2026-13-000002');
    }

    public function test_password_change_uses_new_password_only_and_clears_required_flag(): void
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
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->actingAs($member, 'member')
            ->postJson('/api/member/password', [
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
