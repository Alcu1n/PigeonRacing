<?php

// [IN]: Member credential import service, spreadsheet files, and database / 会员登录凭据导入服务、表格文件与数据库
// [OUT]: Credential import parsing, preview, commit, and report assertions / 登录凭据导入解析、预览、写入与报告断言
// [POS]: Backend member credential import feature test / 后端会员登录凭据导入功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Exports\MemberCredentialImportErrorExport;
use App\Exports\MemberCredentialImportTemplateExport;
use App\Models\Member;
use App\Services\MemberCredentialImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class MemberCredentialImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reads_only_the_exact_three_column_template_and_ignores_blank_rows(): void
    {
        $service = app(MemberCredentialImportService::class);
        $path = $this->makeSheet([
            ['会员棚号', '手机号', '密码'],
            ['A001', '13800000000', 'secret1'],
            ['', '', ''],
        ]);

        $this->assertSame([
            [
                'line' => 2,
                'loft_number' => 'A001',
                'phone' => '13800000000',
                'password' => 'secret1',
            ],
        ], $service->rowsFromSpreadsheet($path));

        foreach ([
            [['手机号', '会员棚号', '密码']],
            [['会员棚号', '手机号', '密码', '备注']],
            [['会员棚号', '手机号', '密码'], ['', '', '']],
        ] as $invalidRows) {
            try {
                $service->rowsFromSpreadsheet($this->makeSheet($invalidRows));
                $this->fail('无效凭据导入表格应被拒绝。');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_preview_classifies_all_business_skips_and_format_errors_without_exposing_passwords(): void
    {
        $this->member('A001');
        $this->member('A002', '13800000002');
        $this->member('A003');
        $this->member('A004');
        $this->member('A005');
        $this->member('A006');
        $this->member('A007');
        $this->member('A008');
        $this->member('OWNER', '13900000099');

        $preview = app(MemberCredentialImportService::class)->preview([
            ['line' => 2, 'loft_number' => 'A001', 'phone' => '13800000001', 'password' => 'valid-secret'],
            ['line' => 3, 'loft_number' => 'A002', 'phone' => '13800000003', 'password' => 'short'],
            ['line' => 4, 'loft_number' => 'A003', 'phone' => '13900000099', 'password' => 'owned-phone'],
            ['line' => 5, 'loft_number' => 'A004', 'phone' => '13800000004', 'password' => 'duplicate-loft-1'],
            ['line' => 6, 'loft_number' => 'A004', 'phone' => '13800000005', 'password' => 'duplicate-loft-2'],
            ['line' => 7, 'loft_number' => 'A005', 'phone' => '13800000006', 'password' => 'duplicate-phone-1'],
            ['line' => 8, 'loft_number' => 'A006', 'phone' => '13800000006', 'password' => 'duplicate-phone-2'],
            ['line' => 9, 'loft_number' => 'A007', 'phone' => '12800000000', 'password' => 'invalid-phone'],
            ['line' => 10, 'loft_number' => 'A008', 'phone' => '13800000008', 'password' => 'short'],
            ['line' => 11, 'loft_number' => 'MISSING', 'phone' => '13800000009', 'password' => 'missing-member'],
        ]);

        $this->assertSame(10, $preview['total_rows']);
        $this->assertSame(1, $preview['valid_rows']);
        $this->assertSame(7, $preview['skipped_rows']);
        $this->assertSame(2, $preview['invalid_rows']);
        $this->assertSame(5, $preview['duplicate_rows']);
        $this->assertSame('ready', $preview['rows'][0]['status']);
        $this->assertSame('skipped', $preview['rows'][1]['status']);
        $this->assertContains('该会员已有手机号', $preview['rows'][1]['errors']);
        $this->assertContains('手机号已属于其他会员', $preview['rows'][2]['errors']);
        $this->assertContains('本次文件内会员棚号重复', $preview['rows'][3]['errors']);
        $this->assertContains('本次文件内会员棚号重复', $preview['rows'][4]['errors']);
        $this->assertContains('本次文件内手机号重复', $preview['rows'][5]['errors']);
        $this->assertContains('本次文件内手机号重复', $preview['rows'][6]['errors']);
        $this->assertSame('invalid', $preview['rows'][7]['status']);
        $this->assertContains('手机号格式不正确', $preview['rows'][7]['errors']);
        $this->assertContains('密码长度必须为 6–128 个字符', $preview['rows'][8]['errors']);
        $this->assertContains('会员棚号不存在', $preview['rows'][9]['errors']);
        $this->assertStringNotContainsString('valid-secret', serialize($preview));
        $this->assertArrayNotHasKey('source_rows', $preview);
        $this->assertTrue($preview['rows'][0]['password_filled']);
    }

    public function test_commit_revalidates_and_updates_only_still_eligible_members_with_a_password_safe_report(): void
    {
        $eligible = Member::query()->create([
            'phone' => null,
            'password' => 'old-password',
            'loft_number' => 'A001',
            'participant_name' => '禁用会员',
            'status' => 'disabled',
            'must_change_password' => false,
        ]);
        $changedAfterPreview = $this->member('A002');
        $rows = [
            ['line' => 2, 'loft_number' => 'A001', 'phone' => '13800000001', 'password' => 'new-secret-1'],
            ['line' => 3, 'loft_number' => 'A002', 'phone' => '13800000002', 'password' => 'new-secret-2'],
        ];
        $service = app(MemberCredentialImportService::class);

        $this->assertSame(2, $service->preview($rows)['valid_rows']);

        $changedAfterPreview->update([
            'phone' => '13900000002',
            'password' => 'concurrent-password',
        ]);

        $batch = $service->commit('member-credentials.xlsx', $rows, null);

        $eligible->refresh();
        $changedAfterPreview->refresh();
        $this->assertSame('13800000001', $eligible->phone);
        $this->assertTrue(Hash::check('new-secret-1', $eligible->password));
        $this->assertTrue($eligible->must_change_password);
        $this->assertSame('disabled', $eligible->status);
        $this->assertSame('13900000002', $changedAfterPreview->phone);
        $this->assertTrue(Hash::check('concurrent-password', $changedAfterPreview->password));
        $this->assertSame(1, $batch->success_rows);
        $this->assertSame(1, $batch->failed_rows);
        $this->assertNotNull($batch->error_report_path);
        $this->assertTrue(Storage::disk('local')->exists($batch->error_report_path));

        $sheet = IOFactory::load(Storage::disk('local')->path($batch->error_report_path))->getActiveSheet();
        $reportRows = $sheet->toArray();
        $this->assertSame(['行号', '会员棚号', '手机号', '错误原因'], $reportRows[0]);
        $this->assertStringNotContainsString('new-secret-2', serialize($reportRows));
        $this->assertStringContainsString('该会员已有手机号', $reportRows[1][3]);
    }

    public function test_all_skipped_rows_still_create_a_downloadable_error_report(): void
    {
        $member = $this->member('A001', '13800000001');
        $member->update(['password' => 'original-password']);

        $batch = app(MemberCredentialImportService::class)->commit('all-skipped.xlsx', [
            ['line' => 2, 'loft_number' => 'A001', 'phone' => '13800000002', 'password' => 'replacement-password'],
        ], null);

        $member->refresh();
        $this->assertSame(0, $batch->success_rows);
        $this->assertSame(1, $batch->failed_rows);
        $this->assertTrue(Storage::disk('local')->exists($batch->error_report_path));
        $this->assertSame('13800000001', $member->phone);
        $this->assertTrue(Hash::check('original-password', $member->password));
    }

    public function test_template_contains_only_the_three_text_formatted_credential_columns(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'member-credential-template-').'.xlsx';
        file_put_contents($path, Excel::raw(new MemberCredentialImportTemplateExport, ExcelFormat::XLSX));

        $sheet = IOFactory::load($path)->getActiveSheet();

        $this->assertSame(['会员棚号', '手机号', '密码'], array_slice($sheet->toArray()[0], 0, 3));
        $this->assertSame('A001', $sheet->getCell('A2')->getValue());
        $this->assertSame('13800000000', (string) $sheet->getCell('B2')->getValue());
        $this->assertSame(NumberFormat::FORMAT_TEXT, $sheet->getStyle('A2')->getNumberFormat()->getFormatCode());
        $this->assertSame(NumberFormat::FORMAT_TEXT, $sheet->getStyle('B2')->getNumberFormat()->getFormatCode());
        $this->assertSame(NumberFormat::FORMAT_TEXT, $sheet->getStyle('C2')->getNumberFormat()->getFormatCode());
        $this->assertSame('C', $sheet->getHighestColumn());
    }

    public function test_error_report_binds_untrusted_cells_as_text_instead_of_formulas(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'member-credential-errors-').'.xlsx';
        $export = new MemberCredentialImportErrorExport([
            [
                'line' => 2,
                'data' => [
                    'loft_number' => '=1+1',
                    'phone' => '=HYPERLINK("https://example.com")',
                ],
                'errors' => ['格式错误'],
            ],
        ]);
        file_put_contents($path, Excel::raw($export, ExcelFormat::XLSX));

        $sheet = IOFactory::load($path)->getActiveSheet();

        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('B2')->getDataType());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('C2')->getDataType());
        $this->assertSame('=1+1', $sheet->getCell('B2')->getValue());
    }

    private function member(string $loftNumber, ?string $phone = null): Member
    {
        return Member::query()->create([
            'phone' => $phone,
            'password' => null,
            'loft_number' => $loftNumber,
            'participant_name' => $loftNumber.' 鸽舍',
            'status' => 'enabled',
        ]);
    }

    private function makeSheet(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows);

        $path = storage_path('framework/testing/member-credential-import-'.uniqid().'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
