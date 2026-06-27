<?php
// [IN]: Member import service, spreadsheet files, and database / 会员导入服务、电子表格文件与数据库
// [OUT]: Member import preview and commit behavior assertions / 会员导入预览与确认行为断言
// [POS]: Backend member import feature test / 后端会员导入功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Models\Member;
use App\Services\MemberImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class MemberImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_standard_member_excel_and_marks_password_change_required(): void
    {
        $service = app(MemberImportService::class);
        $path = $this->makeSheet([
            ['序号', '棚号', '参赛名', '手机号', '密码'],
            [1, 'B001', '新会员鸽舍', '13900000001', 'secret1'],
        ]);

        $preview = $service->preview($service->rowsFromSpreadsheet($path));
        $batch = $service->commit('members.xlsx', $preview, null);

        $member = Member::query()->where('loft_number', 'B001')->firstOrFail();
        $this->assertSame('新会员鸽舍', $member->participant_name);
        $this->assertSame('13900000001', $member->phone);
        $this->assertTrue(Hash::check('secret1', $member->password));
        $this->assertTrue($member->must_change_password);
        $this->assertSame(1, $batch->success_rows);
    }

    public function test_it_rejects_invalid_header(): void
    {
        $this->expectException(ValidationException::class);

        app(MemberImportService::class)->rowsFromSpreadsheet($this->makeSheet([
            ['序号', '会员棚号', '会员参赛名', '手机号', '密码'],
            [1, 'A001', '张三', '13900000001', 'secret1'],
        ]));
    }

    public function test_preview_reports_duplicate_loft_and_phone_conflicts(): void
    {
        Member::query()->create([
            'phone' => '13900000099',
            'password' => 'oldpass',
            'loft_number' => 'EXIST',
            'participant_name' => '已有会员',
            'status' => 'enabled',
        ]);

        $preview = app(MemberImportService::class)->preview([
            ['line' => 2, 'sequence' => '1', 'loft_number' => 'A001', 'participant_name' => '张三', 'phone' => '13900000001', 'password' => 'secret1'],
            ['line' => 3, 'sequence' => '2', 'loft_number' => 'A001', 'participant_name' => '张三重复', 'phone' => '13900000002', 'password' => 'secret2'],
            ['line' => 4, 'sequence' => '3', 'loft_number' => 'A003', 'participant_name' => '李四', 'phone' => '13900000001', 'password' => 'secret3'],
            ['line' => 5, 'sequence' => '4', 'loft_number' => 'A004', 'participant_name' => '王五', 'phone' => '13900000099', 'password' => 'secret4'],
            ['line' => 6, 'sequence' => '5', 'loft_number' => '', 'participant_name' => '', 'phone' => '', 'password' => ''],
        ]);

        $this->assertSame(1, $preview['valid_rows']);
        $this->assertSame(4, $preview['failed_rows']);
        $this->assertSame(3, $preview['duplicate_rows']);
    }

    public function test_existing_member_uses_non_empty_excel_values_without_empty_overwrite(): void
    {
        $member = Member::query()->create([
            'phone' => '13900000010',
            'password' => 'oldpass',
            'loft_number' => 'A010',
            'participant_name' => '旧名',
            'status' => 'enabled',
            'must_change_password' => false,
        ]);

        $service = app(MemberImportService::class);
        $preview = $service->preview([
            ['line' => 2, 'sequence' => '1', 'loft_number' => 'A010', 'participant_name' => '新名', 'phone' => '', 'password' => ''],
        ]);
        $service->commit('members.xlsx', $preview, null);

        $member->refresh();
        $this->assertSame('13900000010', $member->phone);
        $this->assertSame('新名', $member->participant_name);
        $this->assertTrue(Hash::check('oldpass', $member->password));
        $this->assertFalse($member->must_change_password);
    }

    public function test_non_empty_password_resets_existing_member_and_requires_change(): void
    {
        $member = Member::query()->create([
            'phone' => '13900000011',
            'password' => 'oldpass',
            'loft_number' => 'A011',
            'participant_name' => '旧名',
            'status' => 'enabled',
            'must_change_password' => false,
        ]);

        $service = app(MemberImportService::class);
        $preview = $service->preview([
            ['line' => 2, 'sequence' => '1', 'loft_number' => 'A011', 'participant_name' => '新名', 'phone' => '13900000012', 'password' => 'newpass'],
        ]);
        $service->commit('members.xlsx', $preview, null);

        $member->refresh();
        $this->assertSame('13900000012', $member->phone);
        $this->assertTrue(Hash::check('newpass', $member->password));
        $this->assertTrue($member->must_change_password);
    }

    private function makeSheet(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows);

        $path = storage_path('framework/testing/member-import-'.uniqid().'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
