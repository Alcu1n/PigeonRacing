<?php

// [IN]: Member race APIs, publication fields, and registration snapshots / 会员赛事 API、发布字段与报名快照
// [OUT]: Published race detail scope and list visibility assertions / 已发布赛事明细范围与列表可见性断言
// [POS]: Backend published race details feature test / 后端已发布赛事明细功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Enums\RaceStatus;
use App\Enums\RegistrationStatus;
use App\Models\Member;
use App\Models\Pigeon;
use App\Models\ProgressiveStageEntry;
use App\Models\Race;
use App\Models\RaceProject;
use App\Models\Registration;
use App\Models\RegistrationCategory;
use App\Models\RegistrationEntry;
use App\Models\RegistrationEntryPigeon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublishedRaceDetailsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_race_list_can_render_detail_publication_action(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin-publish-details@example.com',
            'password' => 'password',
        ]);
        $admin->assignRole('super-admin');
        $this->race();

        $this->actingAs($admin)
            ->get('/admin/races')
            ->assertOk()
            ->assertSee('明细发布');
    }

    public function test_member_race_list_exposes_published_details_flag_only_after_publication(): void
    {
        $member = $this->member('A001');
        $published = $this->race(['registration_details_published_at' => now()]);
        $hidden = $this->race(['name' => '未发布明细']);

        $this->actingAs($member, 'member')
            ->getJson('/api/member/races')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $published->id,
                'has_published_details' => true,
                'registration_details_scope' => Race::DETAILS_SCOPE_CONFIRMED_ONLY,
            ])
            ->assertJsonFragment([
                'id' => $hidden->id,
                'has_published_details' => false,
            ]);
    }

    public function test_unpublished_details_endpoint_returns_not_found(): void
    {
        $member = $this->member('A001');
        $race = $this->race();

        $this->actingAs($member, 'member')
            ->getJson("/api/member/races/{$race->id}/published-details")
            ->assertNotFound()
            ->assertJsonPath('error_code', 'details_not_published');
    }

    public function test_confirmed_only_scope_returns_only_confirmed_standard_and_progressive_rows(): void
    {
        $member = $this->member('A001');
        $race = $this->race(['registration_details_published_at' => now()]);
        $confirmed = $this->registration($member, $race, RegistrationStatus::Confirmed);
        $pending = $this->registration($this->member('A002'), $race, RegistrationStatus::PendingConfirmation);
        $single = $this->project($race, '单羽 50', 1, 5000, 1);
        $double = $this->project($race, '双羽组', 2, 20000, 2);
        $confirmedPigeons = $this->pigeons($member, ['2026-13-000001', '2026-13-000002']);
        $pendingPigeons = $this->pigeons($pending->member, ['2026-13-000003', '2026-13-000004']);

        $this->entry($confirmed, $single, 1, [$confirmedPigeons[0]]);
        $this->entry($confirmed, $double, 1, $confirmedPigeons);
        $this->entry($pending, $single, 1, [$pendingPigeons[0]]);
        $this->progressiveEntry($race, $confirmed, $confirmedPigeons[0], RegistrationStatus::Confirmed);
        $this->progressiveEntry($race, $pending, $pendingPigeons[0], RegistrationStatus::PendingConfirmation);

        $this->actingAs($member, 'member')
            ->getJson("/api/member/races/{$race->id}/published-details")
            ->assertOk()
            ->assertJsonPath('scope', Race::DETAILS_SCOPE_CONFIRMED_ONLY)
            ->assertJsonPath('single.rows.0.loft_number', 'A001')
            ->assertJsonPath('multi.0.groups.0.loft_number', 'A001')
            ->assertJsonCount(1, 'single.rows')
            ->assertJsonCount(1, 'multi.0.groups')
            ->assertJsonCount(1, 'progressive.0.stages.0.groups');
    }

    public function test_all_submitted_scope_returns_pending_rows_with_status(): void
    {
        $member = $this->member('A001');
        $race = $this->race([
            'registration_details_published_at' => now(),
            'registration_details_scope' => Race::DETAILS_SCOPE_ALL_SUBMITTED,
        ]);
        $pending = $this->registration($member, $race, RegistrationStatus::PendingConfirmation);
        $single = $this->project($race, '单羽 50', 1, 5000, 1);
        $pigeon = $this->pigeons($member, ['2026-13-000005'])[0];
        $this->entry($pending, $single, 1, [$pigeon]);

        $this->actingAs($member, 'member')
            ->getJson("/api/member/races/{$race->id}/published-details")
            ->assertOk()
            ->assertJsonPath('scope', Race::DETAILS_SCOPE_ALL_SUBMITTED)
            ->assertJsonPath('single.rows.0.selected_projects.'.$single->id, RegistrationStatus::PendingConfirmation->value);
    }

    public function test_progressive_details_only_return_current_open_stage(): void
    {
        $member = $this->member('A001');
        $race = $this->race(['registration_details_published_at' => now()]);
        $registration = $this->registration($member, $race, RegistrationStatus::Confirmed);
        $category = RegistrationCategory::query()->create([
            'race_id' => $race->id,
            'name' => '站站赛',
            'type' => RegistrationCategory::TYPE_PROGRESSIVE,
            'is_enabled' => true,
        ]);
        $firstStage = $this->progressiveProject($race, $category, '第一阶段', 1);
        $secondStage = $this->progressiveProject($race, $category, '第二阶段', 2);
        $category->forceFill(['current_stage_project_id' => $secondStage->id])->save();
        $pigeons = $this->pigeons($member, ['2026-13-000006', '2026-13-000007']);
        $this->progressiveEntryForProject($race, $registration, $category, $firstStage, $pigeons[0], RegistrationStatus::Confirmed);
        $this->progressiveEntryForProject($race, $registration, $category, $secondStage, $pigeons[1], RegistrationStatus::Confirmed);

        $this->actingAs($member, 'member')
            ->getJson("/api/member/races/{$race->id}/published-details")
            ->assertOk()
            ->assertJsonCount(1, 'progressive.0.stages')
            ->assertJsonPath('progressive.0.stages.0.stage_project_name', '第二阶段')
            ->assertJsonPath('progressive.0.stages.0.groups.0.rings.0', '2026-13-000007');
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

    private function registration(Member $member, Race $race, RegistrationStatus $status): Registration
    {
        return Registration::query()->create([
            'registration_no' => 'R-'.$member->loft_number.'-'.Str::random(4),
            'race_id' => $race->id,
            'member_id' => $member->id,
            'total_amount_cent' => 5000,
            'status' => $status,
            'idempotency_key' => (string) Str::uuid(),
            'submitted_at' => now(),
            'confirmed_at' => $status === RegistrationStatus::Confirmed ? now() : null,
        ]);
    }

    private function project(Race $race, string $name, int $groupSize, int $priceCent, int $sortOrder): RaceProject
    {
        return RaceProject::query()->create([
            'race_id' => $race->id,
            'name' => $name,
            'group_size' => $groupSize,
            'price_cent' => $priceCent,
            'sort_order' => $sortOrder,
            'is_enabled' => true,
        ]);
    }

    /**
     * @return array<int, Pigeon>
     */
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

        foreach ($pigeons as $index => $pigeon) {
            RegistrationEntryPigeon::query()->create([
                'registration_entry_id' => $entry->id,
                'pigeon_id' => $pigeon->id,
                'ring_number_snapshot' => $pigeon->ring_number,
                'sort_order' => $index + 1,
                'created_at' => now(),
            ]);
        }
    }

    private function progressiveEntry(Race $race, Registration $registration, Pigeon $pigeon, RegistrationStatus $status): void
    {
        $category = RegistrationCategory::query()->firstOrCreate(
            ['race_id' => $race->id, 'name' => '站站赛'],
            ['is_enabled' => true, 'type' => RegistrationCategory::TYPE_PROGRESSIVE],
        );
        $project = RaceProject::query()->firstOrCreate(
            ['race_id' => $race->id, 'registration_category_id' => $category->id, 'name' => '第一阶段'],
            [
                'project_type' => RaceProject::TYPE_PROGRESSIVE_STAGE,
                'stage_order' => 1,
                'group_size' => 1,
                'price_cent' => 1000,
                'sort_order' => 1,
                'is_enabled' => true,
            ],
        );
        $category->forceFill(['current_stage_project_id' => $project->id])->save();

        $this->progressiveEntryForProject($race, $registration, $category, $project, $pigeon, $status);
    }

    private function progressiveProject(Race $race, RegistrationCategory $category, string $name, int $stageOrder): RaceProject
    {
        return RaceProject::query()->create([
            'race_id' => $race->id,
            'project_type' => RaceProject::TYPE_PROGRESSIVE_STAGE,
            'registration_category_id' => $category->id,
            'stage_order' => $stageOrder,
            'name' => $name,
            'group_size' => 1,
            'price_cent' => 1000,
            'sort_order' => $stageOrder,
            'is_enabled' => true,
        ]);
    }

    private function progressiveEntryForProject(
        Race $race,
        Registration $registration,
        RegistrationCategory $category,
        RaceProject $project,
        Pigeon $pigeon,
        RegistrationStatus $status,
    ): void {
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
            'confirmed_at' => $status === RegistrationStatus::Confirmed ? now() : null,
        ]);
    }
}
