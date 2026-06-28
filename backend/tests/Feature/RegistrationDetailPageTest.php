<?php
// [IN]: Filament admin session and registration snapshots / Filament 后台会话与报名快照
// [OUT]: Registration detail matrix render assertions / 报名详情矩阵渲染断言
// [POS]: Backend admin registration detail feature test / 后端后台报名详情功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Enums\RaceStatus;
use App\Enums\RegistrationStatus;
use App\Filament\Resources\RegistrationResource;
use App\Models\Member;
use App\Models\Pigeon;
use App\Models\Race;
use App\Models\RaceProject;
use App\Models\Registration;
use App\Models\RegistrationEntry;
use App\Models\RegistrationEntryPigeon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegistrationDetailPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_see_registration_entry_details(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin-detail@example.com',
            'password' => 'password',
        ]);
        $registration = $this->registrationWithEntries();

        $this->actingAs($admin)
            ->get(RegistrationResource::getUrl('view', ['record' => $registration]))
            ->assertOk()
            ->assertSee('单羽项目矩阵')
            ->assertSee('多羽组明细')
            ->assertSee('A001')
            ->assertSee('张三鸽舍')
            ->assertSeeInOrder(['足环号', '单羽 50', '单羽 100'])
            ->assertSee('单羽 50')
            ->assertSee('单羽 100')
            ->assertSee('双羽组 200')
            ->assertSee('第1羽')
            ->assertSee('第2羽')
            ->assertSee('2026-13-000001')
            ->assertSee('2026-13-000002');
    }

    private function registrationWithEntries(): Registration
    {
        $member = Member::query()->create([
            'phone' => '13900000009',
            'password' => 'password',
            'loft_number' => 'A001',
            'participant_name' => '张三鸽舍',
            'status' => 'enabled',
        ]);
        $race = Race::query()->create([
            'name' => '测试赛事',
            'registration_start_at' => now()->subDay(),
            'registration_end_at' => now()->addDay(),
            'status' => RaceStatus::Published,
            'is_visible' => true,
        ]);
        $single = RaceProject::query()->create([
            'race_id' => $race->id,
            'name' => '单羽 50',
            'group_size' => 1,
            'price_cent' => 5000,
            'sort_order' => 1,
        ]);
        $singleSecond = RaceProject::query()->create([
            'race_id' => $race->id,
            'name' => '单羽 100',
            'group_size' => 1,
            'price_cent' => 10000,
            'sort_order' => 2,
        ]);
        $double = RaceProject::query()->create([
            'race_id' => $race->id,
            'name' => '双羽组 200',
            'group_size' => 2,
            'price_cent' => 20000,
            'sort_order' => 3,
        ]);
        $pigeons = collect([1, 2])->mapWithKeys(fn (int $number): array => [
            $number => Pigeon::query()->create([
                'member_id' => $member->id,
                'loft_number' => $member->loft_number,
                'participant_name' => $member->participant_name,
                'ring_number' => sprintf('2026-13-%06d', $number),
                'status' => 'normal',
            ]),
        ]);
        $registration = Registration::query()->create([
            'registration_no' => 'REG-DETAIL',
            'race_id' => $race->id,
            'member_id' => $member->id,
            'total_amount_cent' => 35000,
            'status' => RegistrationStatus::Submitted,
            'idempotency_key' => (string) Str::uuid(),
            'submitted_at' => now(),
        ]);

        $this->entry($registration, $single, 1, [$pigeons[1]]);
        $this->entry($registration, $singleSecond, 1, [$pigeons[1]]);
        $this->entry($registration, $double, 1, [$pigeons[1], $pigeons[2]]);

        return $registration;
    }

    private function entry(Registration $registration, RaceProject $project, int $groupIndex, array $pigeons): void
    {
        $entry = RegistrationEntry::query()->create([
            'registration_id' => $registration->id,
            'race_project_id' => $project->id,
            'project_name_snapshot' => $project->name,
            'group_size_snapshot' => $project->group_size,
            'price_cent_snapshot' => $project->price_cent,
            'group_index' => $groupIndex,
            'created_at' => now(),
        ]);

        foreach (array_values($pigeons) as $index => $pigeon) {
            RegistrationEntryPigeon::query()->create([
                'registration_entry_id' => $entry->id,
                'pigeon_id' => $pigeon->id,
                'ring_number_snapshot' => $pigeon->ring_number,
                'sort_order' => $index + 1,
                'created_at' => now(),
            ]);
        }
    }
}
