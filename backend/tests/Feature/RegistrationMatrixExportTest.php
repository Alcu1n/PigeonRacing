<?php
// [IN]: Registration matrix export and persisted snapshots / 报名矩阵导出与已持久化快照
// [OUT]: Export heading and cell expansion assertions / 导出表头与单元格展开断言
// [POS]: Backend registration export feature test / 后端报名导出功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Enums\RaceStatus;
use App\Enums\RegistrationStatus;
use App\Exports\RegistrationMatrixExport;
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

class RegistrationMatrixExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exports_registration_rows_as_project_matrix(): void
    {
        [$race] = $this->fixtures();
        $export = new RegistrationMatrixExport($race->id);

        $this->assertSame(['序号', '会员棚号', '会员参赛名', '足环号码', '单羽 50', '双羽组 200'], $export->headings());
        $this->assertSame([
            [1, 'A001', '张三鸽舍', '2026-13-000001', '✓', '第1组，第2组'],
            [2, 'A001', '张三鸽舍', '2026-13-000002', '', '第1组'],
            [3, 'A001', '张三鸽舍', '2026-13-000003', '', '第2组'],
        ], $export->collection()->values()->all());
    }

    private function fixtures(): array
    {
        $member = Member::query()->create([
            'phone' => '13900000003',
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
        $single = RaceProject::query()->create(['race_id' => $race->id, 'name' => '单羽 50', 'group_size' => 1, 'price_cent' => 5000, 'sort_order' => 1]);
        $double = RaceProject::query()->create(['race_id' => $race->id, 'name' => '双羽组 200', 'group_size' => 2, 'price_cent' => 20000, 'sort_order' => 2, 'allow_repeat_pigeon_in_project' => true]);
        $pigeons = collect([1, 2, 3])->mapWithKeys(fn (int $number): array => [
            $number => Pigeon::query()->create([
                'member_id' => $member->id,
                'loft_number' => $member->loft_number,
                'participant_name' => $member->participant_name,
                'ring_number' => sprintf('2026-13-%06d', $number),
                'status' => 'normal',
            ]),
        ]);
        $registration = Registration::query()->create([
            'registration_no' => 'REG001',
            'race_id' => $race->id,
            'member_id' => $member->id,
            'total_amount_cent' => 45000,
            'status' => RegistrationStatus::Submitted,
            'idempotency_key' => (string) Str::uuid(),
            'submitted_at' => now(),
        ]);

        $this->entry($registration, $single, 1, [$pigeons[1]]);
        $this->entry($registration, $double, 1, [$pigeons[1], $pigeons[2]]);
        $this->entry($registration, $double, 2, [$pigeons[1], $pigeons[3]]);

        return [$race];
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
