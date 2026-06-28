<?php
// [IN]: Filament admin session and registration totals / Filament 后台会话与报名汇总
// [OUT]: Registration list summary render assertions / 报名列表汇总渲染断言
// [POS]: Backend admin registration list summary feature test / 后端后台报名列表汇总功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Enums\RaceStatus;
use App\Enums\RegistrationStatus;
use App\Models\Member;
use App\Models\Race;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegistrationListSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_see_registration_summary_above_list(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin-summary@example.com',
            'password' => 'password',
        ]);
        $race = Race::query()->create([
            'name' => '测试赛事',
            'registration_start_at' => now()->subDay(),
            'registration_end_at' => now()->addDay(),
            'status' => RaceStatus::Published,
            'is_visible' => true,
        ]);

        $this->registration($race, 'A001', RegistrationStatus::Confirmed, 5000);
        $this->registration($race, 'A002', RegistrationStatus::PendingConfirmation, 20000);

        $this->actingAs($admin)
            ->get('/admin/registrations')
            ->assertOk()
            ->assertSee('已报名总金额')
            ->assertSee('250 元')
            ->assertSee('已确认金额')
            ->assertSee('50 元')
            ->assertSee('未确认金额')
            ->assertSee('200 元')
            ->assertSee('报名总棚数')
            ->assertSee('2 棚');
    }

    private function registration(Race $race, string $loftNumber, RegistrationStatus $status, int $amountCent): void
    {
        $member = Member::query()->create([
            'phone' => null,
            'password' => null,
            'loft_number' => $loftNumber,
            'participant_name' => $loftNumber.'鸽舍',
            'status' => 'enabled',
        ]);

        Registration::query()->create([
            'registration_no' => 'REG-'.$loftNumber,
            'race_id' => $race->id,
            'member_id' => $member->id,
            'total_amount_cent' => $amountCent,
            'status' => $status,
            'idempotency_key' => (string) Str::uuid(),
            'submitted_at' => now(),
            'confirmed_at' => $status === RegistrationStatus::Confirmed ? now() : null,
        ]);
    }
}
