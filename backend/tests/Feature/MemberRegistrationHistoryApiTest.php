<?php

// [IN]: Member registration history API, races, and persisted snapshots / 会员报名历史 API、赛事与持久化快照
// [OUT]: Latest-per-race history list, immutable display identity, and ownership assertions / 每赛事最新报名历史列表、不可变展示身份与归属断言
// [POS]: Backend member registration history feature test / 后端会员报名历史功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Enums\RaceStatus;
use App\Enums\RegistrationStatus;
use App\Models\Member;
use App\Models\Pigeon;
use App\Models\Race;
use App\Models\RaceProject;
use App\Models\Registration;
use App\Models\RegistrationEntry;
use App\Models\RegistrationEntryPigeon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberRegistrationHistoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_history_returns_own_registrations_ordered_by_latest_submit_with_counts(): void
    {
        $member = $this->member('A001');
        $other = $this->member('A002');
        $race = $this->race('春赛');
        $latest = $this->registration($member, $race, 25000, now()->subDay());
        $olderRace = $this->race('秋赛');
        $older = $this->registration($member, $olderRace, 5000, now()->subDays(2));
        $this->registration($other, $race, 99900, now());

        $single = $this->project($race, '单羽 50', 1, 5000);
        $double = $this->project($race, '双羽组 200', 2, 20000);
        $olderSingle = $this->project($olderRace, '单羽 50', 1, 5000);
        $pigeons = $this->pigeons($member, ['2026-13-000001', '2026-13-000002']);
        $this->entry($older, $olderSingle, 1, [$pigeons[0]]);
        $this->entry($latest, $single, 1, [$pigeons[0]]);
        $this->entry($latest, $double, 1, [$pigeons[0], $pigeons[1]]);

        $this->actingAs($member, 'member')
            ->getJson('/api/member/registrations')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.registration_id', $latest->id)
            ->assertJsonPath('0.race_name', '春赛')
            ->assertJsonPath('0.total_amount_cent', 25000)
            ->assertJsonPath('0.single_count', 1)
            ->assertJsonPath('0.multi_group_count', 1)
            ->assertJsonPath('1.registration_id', $older->id)
            ->assertJsonPath('1.race_name', '秋赛');
    }

    public function test_member_cannot_open_another_members_registration_detail(): void
    {
        $owner = $this->member('A001');
        $viewer = $this->member('A002');
        $registration = $this->registration($owner, $this->race('秋赛'), 5000, now());

        $this->actingAs($viewer, 'member')
            ->getJson("/api/member/registrations/{$registration->id}")
            ->assertNotFound()
            ->assertJsonPath('error_code', 'registration_not_found');
    }

    public function test_registration_detail_uses_submission_snapshots_after_related_names_change(): void
    {
        $member = $this->member('A001');
        $race = $this->race('报名时赛事');
        $registration = $this->registration($member, $race, 5000, now());
        $registration->forceFill([
            'race_name_snapshot' => '报名时赛事',
            'loft_number_snapshot' => 'A001',
            'participant_name_snapshot' => 'A001鸽舍',
        ])->save();

        $member->forceFill(['loft_number' => 'A999', 'participant_name' => '新参赛名'])->save();
        $race->forceFill(['name' => '改名后赛事'])->save();

        $this->actingAs($member, 'member')
            ->getJson("/api/member/registrations/{$registration->id}")
            ->assertOk()
            ->assertJsonPath('race_name', '报名时赛事')
            ->assertJsonPath('loft_number', 'A001')
            ->assertJsonPath('participant_name', 'A001鸽舍');
    }

    public function test_registration_detail_falls_back_to_current_related_names_when_snapshots_are_missing(): void
    {
        $member = $this->member('A001');
        $race = $this->race('旧记录赛事');
        $registration = $this->registration($member, $race, 5000, now());

        $this->actingAs($member, 'member')
            ->getJson("/api/member/registrations/{$registration->id}")
            ->assertOk()
            ->assertJsonPath('race_name', '旧记录赛事')
            ->assertJsonPath('loft_number', 'A001')
            ->assertJsonPath('participant_name', 'A001鸽舍');
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

    private function race(string $name): Race
    {
        return Race::query()->create([
            'name' => $name,
            'registration_start_at' => now()->subDays(10),
            'registration_end_at' => now()->addDays(10),
            'status' => RaceStatus::Published,
            'is_visible' => true,
        ]);
    }

    private function registration(Member $member, Race $race, int $amountCent, mixed $submittedAt): Registration
    {
        return Registration::query()->create([
            'registration_no' => 'REG-'.Str::random(8),
            'race_id' => $race->id,
            'member_id' => $member->id,
            'total_amount_cent' => $amountCent,
            'status' => RegistrationStatus::Submitted,
            'idempotency_key' => (string) Str::uuid(),
            'submitted_at' => $submittedAt,
        ]);
    }

    private function project(Race $race, string $name, int $groupSize, int $priceCent): RaceProject
    {
        return RaceProject::query()->create([
            'race_id' => $race->id,
            'name' => $name,
            'group_size' => $groupSize,
            'price_cent' => $priceCent,
            'sort_order' => $groupSize,
        ]);
    }

    private function pigeons(Member $member, array $rings): array
    {
        return collect($rings)->map(fn (string $ring): Pigeon => Pigeon::query()->create([
            'member_id' => $member->id,
            'loft_number' => $member->loft_number,
            'participant_name' => $member->participant_name,
            'ring_number' => $ring,
            'status' => 'normal',
        ]))->all();
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
