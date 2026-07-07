<?php
// [IN]: Progressive categories, stage projects, imports, and submissions / 递进类别、阶段项目、导入与提交
// [OUT]: First-stage import and previous-stage eligibility assertions / 第一阶段导入与上一阶段资格断言
// [POS]: Backend progressive stage registration feature tests / 后端递进阶段报名功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Enums\RaceStatus;
use App\Enums\RegistrationStatus;
use App\Exports\ProgressiveStageImportTemplateExport;
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

    public function test_first_stage_reimport_replaces_previous_import_baseline(): void
    {
        [, $category, $stage] = $this->progressiveFixtures();
        $service = app(ProgressiveStageImportService::class);
        $firstRows = $service->rowsFromSpreadsheet($this->makeSheet([
            ['序号', '会员棚号', '会员参赛名', '足环号码', $stage->name],
            [1, 'A001', '张三鸽舍', '2026-13-000101', '✓'],
            [2, 'A001', '张三鸽舍', '2026-13-000102', '✓'],
        ]), $category);
        $service->commitStoredPreview($category, 'first.xlsx', $service->storeRowsForConfirmation($firstRows), null);

        $secondRows = $service->rowsFromSpreadsheet($this->makeSheet([
            ['序号', '会员棚号', '会员参赛名', '足环号码', $stage->name],
            [1, 'A001', '张三鸽舍', '2026-13-000102', '✓'],
            [2, 'A001', '张三鸽舍', '2026-13-000103', '✓'],
        ]), $category);
        $batch = $service->commitStoredPreview($category, 'second.xlsx', $service->storeRowsForConfirmation($secondRows), null);

        $this->assertSame(2, $batch->success_rows);
        $this->assertDatabaseMissing('progressive_stage_entries', [
            'race_project_id' => $stage->id,
            'ring_number_snapshot' => '2026-13-000101',
            'source' => ProgressiveStageEntry::SOURCE_IMPORT,
        ]);
        $this->assertDatabaseHas('progressive_stage_entries', [
            'race_project_id' => $stage->id,
            'ring_number_snapshot' => '2026-13-000102',
            'source' => ProgressiveStageEntry::SOURCE_IMPORT,
            'status' => RegistrationStatus::Confirmed->value,
        ]);
        $this->assertDatabaseHas('progressive_stage_entries', [
            'race_project_id' => $stage->id,
            'ring_number_snapshot' => '2026-13-000103',
            'source' => ProgressiveStageEntry::SOURCE_IMPORT,
            'status' => RegistrationStatus::Confirmed->value,
        ]);
    }

    public function test_first_stage_import_uses_stage_order_one_even_when_current_stage_is_second(): void
    {
        [, $category, $firstStage, $secondStage] = $this->progressiveFixtures(withSecondStage: true);
        $this->assertSame($secondStage->id, $category->current_stage_project_id);

        $service = app(ProgressiveStageImportService::class);
        $rows = $service->rowsFromSpreadsheet($this->makeSheet([
            ['序号', '会员棚号', '会员参赛名', '足环号码', $firstStage->name],
            [1, 'A001', '张三鸽舍', '2026-13-000201', '✓'],
        ]), $category);
        $preview = $service->preview($rows);

        $this->assertSame('福安 1.5K', $service->firstStage($category)->name);
        $this->assertSame(1, $preview['valid_rows']);
    }

    public function test_progressive_stage_keeps_configured_group_size(): void
    {
        [, , $stage] = $this->progressiveFixtures(groupSize: 3);

        $this->assertSame(3, $stage->fresh()->group_size);
    }

    public function test_multi_pigeon_template_puts_group_rings_in_one_cell(): void
    {
        $rows = (new ProgressiveStageImportTemplateExport('阶段 1', 3))->array();

        $this->assertSame('2025-13-000001，2025-13-000002，2025-13-000003', $rows[0][3]);
    }

    public function test_multi_pigeon_first_stage_import_validates_group_rows_and_usage_limit(): void
    {
        [, $category, $stage] = $this->progressiveFixtures(groupSize: 3);
        $service = app(ProgressiveStageImportService::class);
        $rows = $service->rowsFromSpreadsheet($this->makeSheet([
            ['序号', '会员棚号', '会员参赛名', '足环号码', $stage->name],
            [1, 'A001', '张三鸽舍', '2026-13-000001，2026-13-000002，2026-13-000003', '✓'],
            [2, 'A001', '张三鸽舍', '2026-13-000003，2026-13-000002，2026-13-000001', '✓'],
            [3, 'A001', '张三鸽舍', '2026-13-000001，2026-13-000004，2026-13-000005', '✓'],
            [4, 'A001', '张三鸽舍', '2026-13-000006，2026-13-000006，2026-13-000007', '✓'],
            [5, 'A001', '张三鸽舍', '2026-13-000008，2026-13-000009', '✓'],
        ]), $category);
        $preview = $service->preview($rows, $category);

        $this->assertSame(2, $preview['valid_rows']);
        $this->assertSame(3, $preview['failed_rows']);

        $batch = $service->commitStoredPreview($category, 'groups.xlsx', $service->storeRowsForConfirmation($rows), null);
        $this->assertSame(2, $batch->success_rows);
        $this->assertSame(6, ProgressiveStageEntry::query()->where('race_project_id', $stage->id)->count());
        $this->assertSame(2, ProgressiveStageEntry::query()
            ->where('race_project_id', $stage->id)
            ->get()
            ->groupBy('group_key')
            ->count());

        $stage->forceFill(['max_usage_per_pigeon' => 1])->save();
        $limitedPreview = $service->preview($rows, $category);
        $this->assertSame(1, $limitedPreview['valid_rows']);
    }

    public function test_second_stage_requires_previous_confirmed_whole_group_and_charges_per_group(): void
    {
        [$race, $category, $firstStage, $secondStage] = $this->progressiveFixtures(withSecondStage: true, groupSize: 3);
        $member = Member::query()->create([
            'phone' => '13900000014',
            'password' => 'password',
            'loft_number' => 'A001',
            'participant_name' => '张三鸽舍',
            'status' => 'enabled',
        ]);
        $first = $this->pigeon($member, '2026-13-000301');
        $second = $this->pigeon($member, '2026-13-000302');
        $third = $this->pigeon($member, '2026-13-000303');
        $other = $this->pigeon($member, '2026-13-000304');
        $this->stageGroupEntries($race, $category, $firstStage, $member, [$first, $second, $third], RegistrationStatus::Confirmed);

        $registration = app(RegistrationSubmissionService::class)->submit(
            $member,
            $race,
            $race->config_version,
            (string) Str::uuid(),
            [],
            [[
                'category_id' => $category->id,
                'stage_project_id' => $secondStage->id,
                'groups' => [['pigeon_ids' => [$third->id, $first->id, $second->id]]],
            ]],
        );

        $this->assertSame($secondStage->price_cent, $registration->total_amount_cent);
        $this->assertSame(3, ProgressiveStageEntry::query()->where('registration_id', $registration->id)->count());
        $this->assertSame(1, ProgressiveStageEntry::query()->where('registration_id', $registration->id)->get()->groupBy('group_key')->count());

        $this->expectException(RegistrationRuleException::class);
        $this->expectExceptionMessage('上一阶段已确认足环组');

        app(RegistrationSubmissionService::class)->submit(
            $member,
            $race,
            $race->config_version,
            (string) Str::uuid(),
            [],
            [[
                'category_id' => $category->id,
                'stage_project_id' => $secondStage->id,
                'groups' => [['pigeon_ids' => [$first->id, $second->id, $other->id]]],
            ]],
        );
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

    private function progressiveFixtures(bool $withSecondStage = false, int $groupSize = 1): array
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
            'group_size' => $groupSize,
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
            'group_size' => $groupSize,
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

    private function stageGroupEntries(Race $race, RegistrationCategory $category, RaceProject $stage, Member $member, array $pigeons, RegistrationStatus $status): void
    {
        $groupKey = collect($pigeons)->pluck('id')->sort()->implode(':');
        foreach ($pigeons as $index => $pigeon) {
            ProgressiveStageEntry::query()->create([
                ...$this->stageEntry($race, $category, $stage, $member, $pigeon, $status),
                'group_key' => $groupKey,
                'group_index' => 1,
                'group_size_snapshot' => $stage->group_size,
                'pigeon_sort_order' => $index + 1,
            ]);
        }
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
