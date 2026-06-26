<?php
// [IN]: Pigeon import service, spreadsheet files, and database / 足环导入服务、电子表格文件与数据库
// [OUT]: Import preview and commit behavior assertions / 导入预览与确认行为断言
// [POS]: Backend pigeon import feature test / 后端足环导入功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Pigeon;
use App\Services\PigeonImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PigeonImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reads_standard_excel_headers_and_commits_new_member(): void
    {
        $service = app(PigeonImportService::class);
        $path = $this->makeSheet([
            ['序号', '会员棚号', '会员参赛名', '足环号码'],
            [1, 'B001', '新会员鸽舍', '2026-13-000001'],
        ]);

        $preview = $service->preview($service->rowsFromSpreadsheet($path));
        $memberInsertSql = null;
        DB::listen(function ($query) use (&$memberInsertSql): void {
            if (str_contains($query->sql, 'insert into') && str_contains($query->sql, 'members')) {
                $memberInsertSql = $query->sql;
            }
        });
        $batch = $service->commit('rings.xlsx', $preview, null);

        $member = Member::query()->where('loft_number', 'B001')->firstOrFail();
        $this->assertStringContainsString('phone', $memberInsertSql);
        $this->assertStringContainsString('password', $memberInsertSql);
        $this->assertNull($member->phone);
        $this->assertNull($member->password);
        $this->assertSame('新会员鸽舍', $member->participant_name);
        $this->assertDatabaseHas('pigeons', ['ring_number' => '2026-13-000001', 'member_id' => $member->id]);
        $this->assertSame(1, $batch->success_rows);
    }

    public function test_it_reports_empty_duplicate_and_existing_ring_errors(): void
    {
        $member = Member::query()->create([
            'phone' => '13900000001',
            'password' => 'password',
            'loft_number' => 'A001',
            'participant_name' => '旧名',
            'status' => 'enabled',
        ]);
        Pigeon::query()->create([
            'member_id' => $member->id,
            'loft_number' => 'A001',
            'participant_name' => '旧名',
            'ring_number' => '2026-13-000001',
            'status' => 'normal',
        ]);

        $preview = app(PigeonImportService::class)->preview([
            ['line' => 2, 'sequence' => '1', 'loft_number' => 'A001', 'participant_name' => '新名', 'ring_number' => '2026-13-000001'],
            ['line' => 3, 'sequence' => '2', 'loft_number' => 'A001', 'participant_name' => '新名', 'ring_number' => '2026-13-000002'],
            ['line' => 4, 'sequence' => '3', 'loft_number' => 'A001', 'participant_name' => '新名', 'ring_number' => '2026-13-000002'],
            ['line' => 5, 'sequence' => '4', 'loft_number' => '', 'participant_name' => '', 'ring_number' => ''],
        ]);

        $this->assertSame(1, $preview['valid_rows']);
        $this->assertSame(3, $preview['failed_rows']);
        $this->assertSame(2, $preview['duplicate_rows']);
        $this->assertSame(1, $preview['update_member_name_rows']);
    }

    public function test_it_updates_existing_member_name_from_excel(): void
    {
        Member::query()->create([
            'phone' => '13900000002',
            'password' => 'password',
            'loft_number' => 'A002',
            'participant_name' => '旧名',
            'status' => 'enabled',
        ]);

        $service = app(PigeonImportService::class);
        $preview = $service->preview([
            ['line' => 2, 'sequence' => '1', 'loft_number' => 'A002', 'participant_name' => '新名', 'ring_number' => '2026-13-000003'],
        ]);
        $service->commit('rings.xlsx', $preview, null);

        $this->assertDatabaseHas('members', ['loft_number' => 'A002', 'participant_name' => '新名']);
        $this->assertDatabaseHas('pigeons', ['ring_number' => '2026-13-000003', 'participant_name' => '新名']);
    }

    private function makeSheet(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($rows);

        $path = storage_path('framework/testing/pigeon-import-'.uniqid().'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
