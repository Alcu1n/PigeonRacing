<?php

// [IN]: Pigeon export and persisted pigeon rows / 足环导出与已持久化足环行
// [OUT]: Export heading, row ordering, and workbook data assertions / 导出表头、行排序与工作簿数据断言
// [POS]: Backend pigeon export feature test / 后端足环导出功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Exports\PigeonExport;
use App\Models\Member;
use App\Models\Pigeon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class PigeonExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exports_pigeons_in_loft_and_ring_order(): void
    {
        $memberA = $this->member('A001', '甲鸽舍');
        $memberB = $this->member('B001', '乙鸽舍');

        Carbon::setTestNow('2026-07-08 09:00:00');
        Pigeon::query()->create([
            'member_id' => $memberA->id,
            'loft_number' => $memberA->loft_number,
            'participant_name' => $memberA->participant_name,
            'ring_number' => '2026-13-000001',
            'status' => 'normal',
        ]);

        Carbon::setTestNow('2026-07-08 10:00:00');
        Pigeon::query()->create([
            'member_id' => $memberB->id,
            'loft_number' => $memberB->loft_number,
            'participant_name' => $memberB->participant_name,
            'ring_number' => '2026-13-000003',
            'status' => 'normal',
        ]);
        Carbon::setTestNow();

        $export = new PigeonExport;

        $this->assertSame(['序号', '会员棚号', '会员参赛名', '足环号码', '状态', '创建时间'], $export->headings());
        $this->assertSame([
            [1, 'A001', '甲鸽舍', '2026-13-000001', 'normal', '2026-07-08 09:00:00'],
            [2, 'B001', '乙鸽舍', '2026-13-000003', 'normal', '2026-07-08 10:00:00'],
        ], $export->collection()->all());
    }

    public function test_it_generates_excel_workbook_with_pigeon_headings(): void
    {
        $member = $this->member('A001', '甲鸽舍');
        Pigeon::query()->create([
            'member_id' => $member->id,
            'loft_number' => $member->loft_number,
            'participant_name' => $member->participant_name,
            'ring_number' => '2026-13-000001',
            'status' => 'normal',
        ]);

        $path = tempnam(sys_get_temp_dir(), 'pigeon-export-').'.xlsx';
        file_put_contents($path, Excel::raw(new PigeonExport, ExcelFormat::XLSX));

        $sheet = IOFactory::load($path)->getActiveSheet();

        $this->assertSame('序号', $sheet->getCell('A1')->getValue());
        $this->assertSame('足环号码', $sheet->getCell('D1')->getValue());
        $this->assertSame('2026-13-000001', $sheet->getCell('D2')->getValue());
    }

    private function member(string $loftNumber, string $participantName): Member
    {
        return Member::query()->create([
            'phone' => null,
            'password' => null,
            'loft_number' => $loftNumber,
            'participant_name' => $participantName,
            'status' => 'enabled',
        ]);
    }
}
