<?php
// [IN]: Filament admin session and registration totals / Filament 后台会话与报名汇总
// [OUT]: Registration list summary render assertions / 报名列表汇总渲染断言
// [POS]: Backend admin registration list summary feature test / 后端后台报名列表汇总功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Enums\RaceStatus;
use App\Enums\RegistrationStatus;
use App\Filament\Resources\RegistrationResource;
use App\Models\Member;
use App\Models\Pigeon;
use App\Models\ProgressiveStageEntry;
use App\Models\Race;
use App\Models\RaceProject;
use App\Models\Registration;
use App\Models\RegistrationCategory;
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

    public function test_admin_can_confirm_multiple_registrations_at_once(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin-bulk-confirm@example.com',
            'password' => 'password',
        ]);
        $race = Race::query()->create([
            'name' => '测试赛事',
            'registration_start_at' => now()->subDay(),
            'registration_end_at' => now()->addDay(),
            'status' => RaceStatus::Published,
            'is_visible' => true,
        ]);
        $pending = $this->registration($race, 'A101', RegistrationStatus::PendingConfirmation, 5000);
        $confirmed = $this->registration($race, 'A102', RegistrationStatus::Confirmed, 6000);
        $this->progressiveEntry($race, $pending, RegistrationStatus::PendingConfirmation);

        $this->actingAs($admin);
        $count = RegistrationResource::confirmRegistrations(collect([$pending, $confirmed]));

        $this->assertSame(1, $count);
        $this->assertSame(RegistrationStatus::Confirmed, $pending->fresh()->status);
        $this->assertSame(RegistrationStatus::Confirmed, $confirmed->fresh()->status);
        $this->assertDatabaseHas('progressive_stage_entries', [
            'registration_id' => $pending->id,
            'status' => RegistrationStatus::Confirmed->value,
            'confirmed_by' => $admin->id,
        ]);
    }

    private function registration(Race $race, string $loftNumber, RegistrationStatus $status, int $amountCent): Registration
    {
        $member = Member::query()->create([
            'phone' => null,
            'password' => null,
            'loft_number' => $loftNumber,
            'participant_name' => $loftNumber.'鸽舍',
            'status' => 'enabled',
        ]);

        return Registration::query()->create([
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

    private function progressiveEntry(Race $race, Registration $registration, RegistrationStatus $status): void
    {
        $category = RegistrationCategory::query()->create([
            'race_id' => $race->id,
            'name' => '站站赛',
            'is_enabled' => true,
        ]);
        $project = RaceProject::query()->create([
            'race_id' => $race->id,
            'project_type' => RaceProject::TYPE_PROGRESSIVE_STAGE,
            'registration_category_id' => $category->id,
            'stage_order' => 1,
            'name' => '第一阶段',
            'group_size' => 1,
            'price_cent' => 1000,
            'sort_order' => 1,
            'is_enabled' => true,
        ]);
        $pigeon = Pigeon::query()->create([
            'member_id' => $registration->member_id,
            'loft_number' => $registration->member->loft_number,
            'participant_name' => $registration->member->participant_name,
            'ring_number' => '2026-13-999999',
            'status' => 'normal',
        ]);

        ProgressiveStageEntry::query()->create([
            'registration_id' => $registration->id,
            'race_id' => $race->id,
            'registration_category_id' => $category->id,
            'race_project_id' => $project->id,
            'member_id' => $registration->member_id,
            'group_key' => (string) $pigeon->id,
            'group_index' => 1,
            'group_size_snapshot' => 1,
            'pigeon_id' => $pigeon->id,
            'pigeon_sort_order' => 1,
            'loft_number_snapshot' => $registration->member->loft_number,
            'participant_name_snapshot' => $registration->member->participant_name,
            'ring_number_snapshot' => $pigeon->ring_number,
            'project_name_snapshot' => $project->name,
            'price_cent_snapshot' => $project->price_cent,
            'status' => $status,
            'source' => ProgressiveStageEntry::SOURCE_MEMBER,
            'submitted_at' => now(),
        ]);
    }
}
