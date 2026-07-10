<?php

// [IN]: Member export and persisted member/pigeon rows / 会员导出与已持久化的会员、足环行
// [OUT]: Export heading, row ordering, and workbook data assertions / 导出表头、行排序与工作簿数据断言
// [POS]: Backend member export feature test / 后端会员导出功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Exports\MemberExport;
use App\Models\Member;
use App\Models\Pigeon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class MemberExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exports_members_in_loft_order_without_passwords(): void
    {
        Carbon::setTestNow('2026-07-10 09:00:00');
        $memberB = $this->member('B001', '乙鸽舍', '13900000000');
        Carbon::setTestNow('2026-07-10 10:00:00');
        $memberA = $this->member('A001', '甲鸽舍', null, 'enabled', '重点会员');
        Pigeon::query()->create([
            'member_id' => $memberA->id,
            'loft_number' => $memberA->loft_number,
            'participant_name' => $memberA->participant_name,
            'ring_number' => '2026-13-000001',
            'status' => 'normal',
        ]);
        Carbon::setTestNow();

        $export = new MemberExport;

        $this->assertSame(['序号', '手机号', '会员棚号', '会员参赛名', '足环数量', '状态', '最近登录', '备注', '创建时间'], $export->headings());
        $this->assertSame([
            [1, '', 'A001', '甲鸽舍', 1, 'enabled', '', '重点会员', '2026-07-10 10:00:00'],
            [2, '13900000000', 'B001', '乙鸽舍', 0, 'enabled', '', '', '2026-07-10 09:00:00'],
        ], $export->collection()->all());
    }

    public function test_it_generates_excel_workbook_with_member_headings(): void
    {
        $member = $this->member('A001', '甲鸽舍', '13800000000');

        $path = tempnam(sys_get_temp_dir(), 'member-export-').'.xlsx';
        file_put_contents($path, Excel::raw(new MemberExport, ExcelFormat::XLSX));

        $sheet = IOFactory::load($path)->getActiveSheet();

        $this->assertSame('序号', $sheet->getCell('A1')->getValue());
        $this->assertSame('会员棚号', $sheet->getCell('C1')->getValue());
        $this->assertSame('@', $sheet->getStyle('B2')->getNumberFormat()->getFormatCode());
        $this->assertSame($member->loft_number, $sheet->getCell('C2')->getValue());
        $this->assertSame($member->participant_name, $sheet->getCell('D2')->getValue());
    }

    private function member(string $loftNumber, string $participantName, ?string $phone, string $status = 'enabled', ?string $remark = null): Member
    {
        return Member::query()->create([
            'phone' => $phone,
            'password' => null,
            'loft_number' => $loftNumber,
            'participant_name' => $participantName,
            'status' => $status,
            'remark' => $remark,
        ]);
    }
}
