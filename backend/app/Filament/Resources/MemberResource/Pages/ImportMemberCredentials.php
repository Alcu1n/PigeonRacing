<?php

// [IN]: Uploaded credential Excel file and member credential import service / 已上传的登录凭据 Excel 文件与会员凭据导入服务
// [OUT]: Password-safe preview-confirm member credential import page / 不泄露密码的会员凭据预览确认导入页面
// [POS]: Backend admin member credential import route / 后端后台会员登录凭据导入路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use App\Models\ImportBatch;
use App\Services\MemberCredentialImportService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportMemberCredentials extends Page
{
    use WithFileUploads;

    protected static string $resource = MemberResource::class;

    protected string $view = 'filament.resources.member-resource.pages.import-member-credentials';

    public ?TemporaryUploadedFile $upload = null;

    public ?array $preview = null;

    public ?string $fileName = null;

    public ?array $lastResult = null;

    public static function canAccess(array $parameters = []): bool
    {
        return MemberResource::hasModulePermission('create');
    }

    public function getTitle(): string
    {
        return '导入会员手机号和密码';
    }

    public function updatedUpload(): void
    {
        $this->reset(['preview', 'fileName', 'lastResult']);
    }

    public function previewUpload(MemberCredentialImportService $service): void
    {
        $this->validate([
            'upload' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        $rows = $service->rowsFromSpreadsheet($this->upload->getRealPath());
        $this->preview = $service->preview($rows);
        $this->fileName = $this->upload->getClientOriginalName();
        $this->lastResult = null;
    }

    public function confirmImport(MemberCredentialImportService $service): void
    {
        if (! $this->preview || ! $this->fileName || ! $this->upload) {
            return;
        }

        $rows = $service->rowsFromSpreadsheet($this->upload->getRealPath());
        $batch = $service->commit($this->fileName, $rows, auth()->id());
        $this->lastResult = [
            'batch_id' => $batch->id,
            'success_rows' => $batch->success_rows,
            'failed_rows' => $batch->failed_rows,
            'has_error_report' => filled($batch->error_report_path),
        ];

        Notification::make()
            ->title("导入完成：成功 {$batch->success_rows} 行，跳过/错误 {$batch->failed_rows} 行")
            ->success()
            ->send();

        $this->reset(['upload', 'preview', 'fileName']);
    }

    public function resetImport(): void
    {
        $this->reset(['upload', 'preview', 'fileName', 'lastResult']);
    }

    public function downloadErrorReport(): ?BinaryFileResponse
    {
        $batchId = $this->lastResult['batch_id'] ?? null;
        if (! $batchId) {
            return null;
        }

        $batch = ImportBatch::query()
            ->whereKey($batchId)
            ->where('uploaded_by', auth()->id())
            ->firstOrFail();
        if (! $batch->error_report_path) {
            return null;
        }

        $fullPath = storage_path('app/private/'.$batch->error_report_path);
        abort_unless(is_file($fullPath), 404);

        return response()->download($fullPath);
    }
}
