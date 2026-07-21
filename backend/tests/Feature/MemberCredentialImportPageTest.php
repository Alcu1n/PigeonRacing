<?php

// [IN]: Filament admin session and member credential import routes / Filament 后台会话与会员登录凭据导入路由
// [OUT]: Parallel member and credential import action/page assertions / 会员导入与凭据导入并行操作及页面断言
// [POS]: Backend member credential import page feature test / 后端会员登录凭据导入页面测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Filament\Resources\MemberResource;
use App\Filament\Resources\MemberResource\Pages\ImportMemberCredentials;
use App\Filament\Resources\MemberResource\Pages\ListMembers;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class MemberCredentialImportPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_parallel_member_and_credential_import_workflows(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $admin->assignRole('super-admin');

        $method = new ReflectionMethod(ListMembers::class, 'getHeaderActions');
        $actions = collect($method->invoke(new ListMembers))->keyBy(fn ($action): string => $action->getName());

        $this->assertSame('导入 Excel', $actions->get('importExcel')?->getLabel());
        $this->assertSame('下载模板', $actions->get('downloadTemplate')?->getLabel());
        $this->assertSame('导出 Excel', $actions->get('exportExcel')?->getLabel());
        $this->assertSame('导入手机号密码', $actions->get('importCredentials')?->getLabel());
        $this->assertSame('下载手机号密码模板', $actions->get('downloadCredentialTemplate')?->getLabel());

        $this->actingAs($admin)
            ->get(MemberResource::getUrl('import-credentials'))
            ->assertOk()
            ->assertSee('会员棚号、手机号、密码')
            ->assertSee('已有手机号的会员将整行跳过')
            ->assertSee('预览导入');
    }

    public function test_selecting_another_file_invalidates_the_existing_preview(): void
    {
        $page = new ImportMemberCredentials;
        $page->preview = ['valid_rows' => 1];
        $page->fileName = 'previewed.xlsx';
        $page->lastResult = ['success_rows' => 1];

        $page->updatedUpload();

        $this->assertNull($page->preview);
        $this->assertNull($page->fileName);
        $this->assertNull($page->lastResult);
    }

    public function test_error_report_download_cannot_use_another_admins_batch_or_a_client_path(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $otherAdmin = User::query()->create([
            'name' => 'Other Admin',
            'email' => 'other-admin@example.com',
            'password' => 'password',
        ]);
        $batch = ImportBatch::query()->create([
            'file_name' => 'credentials.xlsx',
            'total_rows' => 1,
            'success_rows' => 0,
            'failed_rows' => 1,
            'duplicate_rows' => 0,
            'uploaded_by' => $otherAdmin->id,
            'status' => 'completed',
            'error_report_path' => 'imports/reports/report.xlsx',
        ]);
        $page = new ImportMemberCredentials;
        $page->lastResult = [
            'batch_id' => $batch->id,
            'error_report_path' => '../../../.env',
        ];
        $this->actingAs($admin);

        $this->expectException(ModelNotFoundException::class);

        $page->downloadErrorReport();
    }
}
