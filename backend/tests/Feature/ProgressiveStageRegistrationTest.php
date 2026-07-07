<?php
// [IN]: Progressive categories, stage projects, imports, and submissions / 递进类别、阶段项目、导入与提交
// [OUT]: First-stage import and previous-stage eligibility assertions / 第一阶段导入与上一阶段资格断言
// [POS]: Backend progressive stage registration feature tests / 后端递进阶段报名功能测试
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
use App\Models\RegistrationCategory;
use App\Services\ProgressiveStageImportService;
use App\Services\RegistrationRuleException;
use App\Services\RegistrationSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ProgressiveStageRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_stage_import_creates_confirmed_baseline_with_system_member_name(): void
    {
        [$race, $category, $stage] = $this->progressiveFixtures();
        Member::query()->create([
            'phone' => '13900000011',
            'password' => 'password',
            'loft_number' => 'A001',
            'participant_name' => '系统鸽舍',
            'status' => 'enabled',
        ]);
        $path = $this->makeSheet([
            ['序号', '会员棚号', '会员参赛名', '足环号码', $stage->name],
            [1, 'A001', 'Excel鸽舍', '2026-13-000001', '✓'],
            [2, 'B002', '新会员鸽舍', '2026-13-000002', 'yes'],
            [3, 'A001', 'Excel鸽舍', '2026-13-000003', '×'],
        ]);

        $service = app(ProgressiveStageImportService::class);
        $rows = $service->rowsFromSpreadsheet($path, $category);
        $preview = $service->preview($rows);
        $token = $service->storeRowsForConfirmation($rows);
        $batch = $service->commitStoredPreview($category, 'baseline.xlsx', $token, null);

        $this->assertSame(2, $preview['valid_rows']);
        $this->assertSame(2, $batch->success_rows);
        $this->assertDatabaseHas('members', ['loft_number' => 'A001', 'participant_name' => '系统鸽舍']);
        $this->assertDatabaseHas('members', ['loft_number' => 'B002', 'participant_name' => '新会员鸽舍']);
        $this->assertDatabaseHas('pigeons', ['ring_number' => '2026-13-000001', 'participant_name' => '系统鸽舍']);
        $this->assertDatabaseHas('progressive_stage_entries', [
            'race_id' => $race->id,
            'registration_category_id' => $category->id,
            'race_project_id' => $stage->id,
            'ring_number_snapshot' => '2026-13-000001',
            'participant_name_snapshot' => '系统鸽舍',
            'status' => RegistrationStatus::Confirmed->value,
            'source' => ProgressiveStageEntry::SOURCE_IMPORT,
        ]);
        $this->assertDatabaseMissing('progressive_stage_entries', ['ring_number_snapshot' => '2026-13-000003']);
    }

    public function test_current_stage_submission_requires_previous_stage_confirmed_pigeon(): void
    {
        [$race, $category, $firstStage, $secondStage] = $this->progressiveFixtures(withSecondStage: true);
        $member = Member::query()->create([
            'phone' => '13900000012',
            'password' => 'password',
            'loft_number' => 'A001',
            'participant_name' => '张三鸽舍',
            'status' => 'enabled',
        ]);
        $eligible = $this->pigeon($member, '2026-13-000001');
        $notEligible = $this->pigeon($member, '2026-13-000002');
        ProgressiveStageEntry::query()->create($this->stageEntry($race, $category, $firstStage, $member, $eligible, RegistrationStatus::Confirmed));
        ProgressiveStageEntry::query()->create($this->stageEntry($race, $category, $firstStage, $member, $notEligible, RegistrationStatus::PendingConfirmation));

        $registration = app(RegistrationSubmissionService::class)->submit(
            $member,
            $race,
            $race->config_version,
            (string) Str::uuid(),
            [],
            [[
                'category_id' => $category->id,
                'stage_project_id' => $secondStage->id,
                'pigeon_ids' => [$eligible->id],
            ]],
        );

        $this->assertSame($secondStage->price_cent, $registration->total_amount_cent);
        $this->assertDatabaseHas('progressive_stage_entries', [
            'registration_id' => $registration->id,
            'race_project_id' => $secondStage->id,
            'pigeon_id' => $eligible->id,
            'status' => RegistrationStatus::PendingConfirmation->value,
        ]);

        $this->expectException(RegistrationRuleException::class);
        $this->expectExceptionMessage('上一阶段已确认足环');

        app(RegistrationSubmissionService::class)->submit(
            $member,
            $race,
            $race->config_version,
            (string) Str::uuid(),
            [],
            [[
                'category_id' => $category->id,
                'stage_project_id' => $secondStage->id,
                'pigeon_ids' => [$notEligible->id],
            ]],
        );
    }

    public function test_confirmed_current_stage_becomes_pending_when_member_changes_selection(): void
    {
        [$race, $category, $firstStage, $secondStage] = $this->progressiveFixtures(withSecondStage: true);
        $member = Member::query()->create([
            'phone' => '13900000013',
            'password' => 'password',
            'loft_number' => 'A001',
            'participant_name' => '张三鸽舍',
            'status' => 'enabled',
        ]);
        $firstPigeon = $this->pigeon($member, '2026-13-000011');
        $secondPigeon = $this->pigeon($member, '2026-13-000012');
        ProgressiveStageEntry::query()->create($this->stageEntry($race, $category, $firstStage, $member, $firstPigeon, RegistrationStatus::Confirmed));
        ProgressiveStageEntry::query()->create($this->stageEntry($race, $category, $firstStage, $member, $secondPigeon, RegistrationStatus::Confirmed));
        ProgressiveStageEntry::query()->create($this->stageEntry($race, $category, $secondStage, $member, $firstPigeon, RegistrationStatus::Confirmed));

        app(RegistrationSubmissionService::class)->submit(
            $member,
            $race,
            $race->config_version,
            (string) Str::uuid(),
            [],
            [[
                'category_id' => $category->id,
                'stage_project_id' => $secondStage->id,
                'pigeon_ids' => [$firstPigeon->id],
            ]],
        );

        $this->assertDatabaseHas('progressive_stage_entries', [
            'race_project_id' => $secondStage->id,
            'pigeon_id' => $firstPigeon->id,
            'status' => RegistrationStatus::Confirmed->value,
        ]);

        app(RegistrationSubmissionService::class)->submit(
            $member,
            $race,
            $race->config_version,
            (string) Str::uuid(),
            [],
            [[
                'category_id' => $category->id,
                'stage_project_id' => $secondStage->id,
                'pigeon_ids' => [$firstPigeon->id, $secondPigeon->id],
            ]],
        );

        $this->assertDatabaseHas('progressive_stage_entries', [
            'race_project_id' => $secondStage->id,
            'pigeon_id' => $firstPigeon->id,
            'status' => RegistrationStatus::PendingConfirmation->value,
        ]);
        $this->assertDatabaseHas('progressive_stage_entries', [
            'race_project_id' => $secondStage->id,
            'pigeon_id' => $secondPigeon->id,
            'status' => RegistrationStatus::PendingConfirmation->value,
        ]);
    }

    private function progressiveFixtures(bool $withSecondStage = false): array
    {
        $race = Race::query()->create([
            'name' => '测试赛事',
            'registration_start_at' => now()->subDay(),
            'registration_end_at' => now()->addDay(),
            'status' => RaceStatus::Published,
            'is_visible' => true,
            'require_admin_confirm' => true,
        ]);
        $category = RegistrationCategory::query()->create([
            'race_id' => $race->id,
            'name' => '站站赛',
            'sort_order' => 1,
            'is_enabled' => true,
        ]);
        $firstStage = RaceProject::query()->create([
            'race_id' => $race->id,
            'project_type' => RaceProject::TYPE_PROGRESSIVE_STAGE,
            'registration_category_id' => $category->id,
            'stage_order' => 1,
            'name' => '福安 1.5K',
            'group_size' => 1,
            'price_cent' => 150000,
            'sort_order' => 1,
            'is_enabled' => true,
        ]);

        if (! $withSecondStage) {
            $category->forceFill(['current_stage_project_id' => $firstStage->id])->save();

            return [$race, $category->fresh('stageProjects'), $firstStage];
        }

        $secondStage = RaceProject::query()->create([
            'race_id' => $race->id,
            'project_type' => RaceProject::TYPE_PROGRESSIVE_STAGE,
            'registration_category_id' => $category->id,
            'stage_order' => 2,
            'name' => '平阳 1.5K',
            'group_size' => 1,
            'price_cent' => 150000,
            'sort_order' => 2,
            'is_enabled' => true,
        ]);
        $category->forceFill(['current_stage_project_id' => $secondStage->id])->save();

        return [$race->fresh(), $category->fresh('stageProjects'), $firstStage, $secondStage];
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

    private function stageEntry(Race $race, RegistrationCategory $category, RaceProject $stage, Member $member, Pigeon $pigeon, RegistrationStatus $status): array
    {
        return [
            'race_id' => $race->id,
            'registration_category_id' => $category->id,
            'race_project_id' => $stage->id,
            'member_id' => $member->id,
            'pigeon_id' => $pigeon->id,
            'loft_number_snapshot' => $member->loft_number,
            'participant_name_snapshot' => $member->participant_name,
            'ring_number_snapshot' => $pigeon->ring_number,
            'project_name_snapshot' => $stage->name,
            'price_cent_snapshot' => $stage->price_cent,
            'status' => $status->value,
            'source' => ProgressiveStageEntry::SOURCE_IMPORT,
            'submitted_at' => now(),
            'confirmed_at' => $status === RegistrationStatus::Confirmed ? now() : null,
        ];
    }

    private function makeSheet(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($rows);

        $path = storage_path('framework/testing/progressive-import-'.uniqid().'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
