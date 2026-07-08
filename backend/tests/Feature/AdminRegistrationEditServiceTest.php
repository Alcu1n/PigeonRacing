<?php
// [IN]: Admin edit service, registration snapshots, and progressive stage rows / 后台编辑服务、报名快照与递进阶段行
// [OUT]: Assertions for admin-edited standard entries, progressive cascade, and totals / 后台编辑普通项目、递进联动与金额断言
// [POS]: Backend admin registration edit feature tests / 后端后台报名编辑功能测试
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
use App\Services\AdminRegistrationEditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminRegistrationEditServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_replace_standard_registration_entries_and_total(): void
    {
        [$race, $member, $registration] = $this->standardFixtures();
        $single = $this->standardProject($race, '单羽 50', 1, 5000);
        $multi = $this->standardProject($race, '双羽 100', 2, 10000);
        $first = $this->pigeon($member, '2026-13-100001');
        $second = $this->pigeon($member, '2026-13-100002');
        $third = $this->pigeon($member, '2026-13-100003');

        app(AdminRegistrationEditService::class)->updateRegistration($registration, [
            'standard_groups' => [
                $single->id => [
                    ['pigeon_ids' => [$first->id]],
                    ['pigeon_ids' => [$second->id]],
                ],
                $multi->id => [
                    ['pigeon_ids' => [$second->id, $third->id]],
                ],
            ],
            'progressive_groups' => [],
        ], null);

        $registration->refresh();
        $this->assertSame(20000, $registration->total_amount_cent);
        $this->assertSame(RegistrationStatus::Confirmed, $registration->status);
        $this->assertSame(3, $registration->entries()->count());
        $this->assertDatabaseHas('registration_entry_pigeons', ['pigeon_id' => $third->id, 'ring_number_snapshot' => '2026-13-100003']);
    }

    public function test_admin_first_stage_replacement_propagates_to_later_progressive_stages(): void
    {
        [$race, $category, $firstStage, $secondStage, $thirdStage] = $this->progressiveFixtures(2, 3);
        [$member, $registration] = $this->memberRegistration($race);
        $first = $this->pigeon($member, '2026-13-200001');
        $second = $this->pigeon($member, '2026-13-200002');
        $third = $this->pigeon($member, '2026-13-200003');
        $fourth = $this->pigeon($member, '2026-13-200004');
        $this->stageGroupEntries($race, $category, $firstStage, $member, [$first, $second], null);
        $this->stageGroupEntries($race, $category, $secondStage, $member, [$first, $second], $registration);
        $this->stageGroupEntries($race, $category, $thirdStage, $member, [$first, $second], $registration);

        app(AdminRegistrationEditService::class)->updateCategoryMember($category, $member, [
            'stage_groups' => [
                $firstStage->id => [['pigeon_ids' => [$third->id, $fourth->id]]],
            ],
        ], null);

        $this->assertSame(0, ProgressiveStageEntry::query()->whereIn('pigeon_id', [$first->id, $second->id])->count());
        $this->assertSame(6, ProgressiveStageEntry::query()->whereIn('pigeon_id', [$third->id, $fourth->id])->count());
        $this->assertSame(1, ProgressiveStageEntry::query()->where('race_project_id', $thirdStage->id)->get()->groupBy('group_key')->count());
    }

    public function test_admin_stage_edit_removes_later_groups_that_are_no_longer_eligible(): void
    {
        [$race, $category, $firstStage, $secondStage] = $this->progressiveFixtures(2, 2);
        [$member, $registration] = $this->memberRegistration($race);
        $first = $this->pigeon($member, '2026-13-300001');
        $second = $this->pigeon($member, '2026-13-300002');
        $third = $this->pigeon($member, '2026-13-300003');
        $fourth = $this->pigeon($member, '2026-13-300004');
        $this->stageGroupEntries($race, $category, $firstStage, $member, [$first, $second], null);
        $this->stageGroupEntries($race, $category, $secondStage, $member, [$third, $fourth], $registration);

        $result = app(AdminRegistrationEditService::class)->updateCategoryMember($category, $member, [
            'stage_groups' => [
                $firstStage->id => [['pigeon_ids' => [$first->id, $second->id]]],
            ],
        ], null);

        $this->assertCount(1, $result['removed_groups']);
        $this->assertSame(0, ProgressiveStageEntry::query()->where('race_project_id', $secondStage->id)->count());
    }

    public function test_admin_registration_edit_cannot_keep_invalid_edited_later_stage_group(): void
    {
        [$race, $category, $firstStage, $secondStage] = $this->progressiveFixtures(2, 2);
        [$member, $registration] = $this->memberRegistration($race);
        $first = $this->pigeon($member, '2026-13-350001');
        $second = $this->pigeon($member, '2026-13-350002');
        $third = $this->pigeon($member, '2026-13-350003');
        $fourth = $this->pigeon($member, '2026-13-350004');

        $result = app(AdminRegistrationEditService::class)->updateRegistration($registration, [
            'standard_groups' => [],
            'progressive_groups' => [
                $category->id => [
                    $firstStage->id => [['pigeon_ids' => [$first->id, $second->id]]],
                    $secondStage->id => [['pigeon_ids' => [$third->id, $fourth->id]]],
                ],
            ],
        ], null);

        $this->assertCount(1, $result['removed_groups']);
        $this->assertSame(0, ProgressiveStageEntry::query()->where('race_project_id', $secondStage->id)->count());
        $this->assertSame(2, ProgressiveStageEntry::query()->where('race_project_id', $firstStage->id)->count());
        $this->assertSame(0, $registration->fresh()->total_amount_cent);
    }

    public function test_admin_editing_first_stage_baseline_does_not_count_registration_total(): void
    {
        [$race, $category, $firstStage] = $this->progressiveFixtures(1, 1);
        [$member, $registration] = $this->memberRegistration($race);
        $pigeon = $this->pigeon($member, '2026-13-400001');

        app(AdminRegistrationEditService::class)->updateRegistration($registration, [
            'standard_groups' => [],
            'progressive_groups' => [
                $category->id => [
                    $firstStage->id => [['pigeon_ids' => [$pigeon->id]]],
                ],
            ],
        ], null);

        $registration->refresh();
        $this->assertSame(0, $registration->total_amount_cent);
        $this->assertDatabaseHas('progressive_stage_entries', [
            'registration_id' => null,
            'race_project_id' => $firstStage->id,
            'pigeon_id' => $pigeon->id,
            'status' => RegistrationStatus::Confirmed->value,
        ]);
    }

    private function standardFixtures(): array
    {
        $race = $this->race();

        return [$race, ...$this->memberRegistration($race)];
    }

    private function progressiveFixtures(int $groupSize, int $stageCount): array
    {
        $race = $this->race();
        $category = RegistrationCategory::query()->create([
            'race_id' => $race->id,
            'name' => '站站赛',
            'sort_order' => 1,
            'is_enabled' => true,
        ]);
        $stages = [];
        foreach (range(1, $stageCount) as $order) {
            $stages[] = RaceProject::query()->create([
                'race_id' => $race->id,
                'project_type' => RaceProject::TYPE_PROGRESSIVE_STAGE,
                'registration_category_id' => $category->id,
                'stage_order' => $order,
                'name' => "第{$order}阶段",
                'group_size' => $groupSize,
                'price_cent' => 10000,
                'sort_order' => $order,
                'is_enabled' => true,
            ]);
        }
        $category->forceFill(['current_stage_project_id' => $stages[array_key_last($stages)]->id])->save();

        return [$race, $category->fresh('stageProjects'), ...$stages];
    }

    private function race(): Race
    {
        return Race::query()->create([
            'name' => '测试赛事',
            'registration_start_at' => now()->subDay(),
            'registration_end_at' => now()->addDay(),
            'status' => RaceStatus::Published,
            'is_visible' => true,
            'require_admin_confirm' => true,
        ]);
    }

    private function memberRegistration(Race $race): array
    {
        $member = Member::query()->create([
            'phone' => fake()->unique()->numerify('139########'),
            'password' => 'password',
            'loft_number' => fake()->unique()->bothify('A###'),
            'participant_name' => '测试鸽舍',
            'status' => 'enabled',
        ]);
        $registration = Registration::query()->create([
            'registration_no' => 'R'.$race->id.'-'.$member->loft_number,
            'race_id' => $race->id,
            'member_id' => $member->id,
            'total_amount_cent' => 0,
            'status' => RegistrationStatus::PendingConfirmation,
            'idempotency_key' => (string) Str::uuid(),
            'submitted_at' => now(),
        ]);

        return [$member, $registration];
    }

    private function standardProject(Race $race, string $name, int $groupSize, int $priceCent): RaceProject
    {
        return RaceProject::query()->create([
            'race_id' => $race->id,
            'project_type' => RaceProject::TYPE_STANDARD,
            'name' => $name,
            'group_size' => $groupSize,
            'price_cent' => $priceCent,
            'sort_order' => $priceCent,
            'is_enabled' => true,
            'allow_repeat_pigeon_in_project' => true,
        ]);
    }

    private function pigeon(Member $member, string $ringNumber): Pigeon
    {
        return Pigeon::query()->create([
            'member_id' => $member->id,
            'loft_number' => $member->loft_number,
            'participant_name' => $member->participant_name,
            'ring_number' => $ringNumber,
            'status' => 'normal',
        ]);
    }

    private function stageGroupEntries(Race $race, RegistrationCategory $category, RaceProject $stage, Member $member, array $pigeons, ?Registration $registration): void
    {
        $groupKey = collect($pigeons)->pluck('id')->sort()->implode(':');
        foreach ($pigeons as $index => $pigeon) {
            ProgressiveStageEntry::query()->create([
                'registration_id' => $registration?->id,
                'race_id' => $race->id,
                'registration_category_id' => $category->id,
                'race_project_id' => $stage->id,
                'member_id' => $member->id,
                'group_key' => $groupKey,
                'group_index' => 1,
                'group_size_snapshot' => $stage->group_size,
                'pigeon_id' => $pigeon->id,
                'pigeon_sort_order' => $index + 1,
                'loft_number_snapshot' => $member->loft_number,
                'participant_name_snapshot' => $member->participant_name,
                'ring_number_snapshot' => $pigeon->ring_number,
                'project_name_snapshot' => $stage->name,
                'price_cent_snapshot' => $stage->price_cent,
                'status' => RegistrationStatus::Confirmed,
                'source' => $registration ? ProgressiveStageEntry::SOURCE_MEMBER : ProgressiveStageEntry::SOURCE_IMPORT,
                'submitted_at' => now(),
                'confirmed_at' => now(),
            ]);
        }
    }
}
