<?php
// [IN]: Uploaded Excel file and member import service / 已上传 Excel 文件与会员导入服务
// [OUT]: Preview-confirm member import page / 预览确认式会员导入页面
// [POS]: Backend admin member import route / 后端后台会员导入路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\MemberResource\Pages;

use App\Filament\Resources\MemberResource;
use App\Services\MemberImportService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportMembers extends Page
{
    use WithFileUploads;

    protected static string $resource = MemberResource::class;

    protected string $view = 'filament.resources.member-resource.pages.import-members';

    public ?TemporaryUploadedFile $upload = null;

    public ?array $preview = null;

    public ?string $fileName = null;

    public ?array $lastResult = null;

    public function getTitle(): string
    {
        return '导入会员 Excel';
    }

    public function previewUpload(MemberImportService $service): void
    {
        $this->validate([
            'upload' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        try {
            $rows = $service->rowsFromSpreadsheet($this->upload->getRealPath());
            $this->preview = $service->preview($rows);
            $this->fileName = $this->upload->getClientOriginalName();
            $this->lastResult = null;
        } catch (ValidationException $exception) {
            throw $exception;
        }
    }

    public function confirmImport(MemberImportService $service): void
    {
        if (! $this->preview || ! $this->fileName) {
            return;
        }

        $batch = $service->commit($this->fileName, $this->preview, auth()->id());
        $this->lastResult = [
            'success_rows' => $batch->success_rows,
            'failed_rows' => $batch->failed_rows,
            'error_report_path' => $batch->error_report_path,
        ];

        Notification::make()
            ->title("导入完成：成功 {$batch->success_rows} 行，失败 {$batch->failed_rows} 行")
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
        $path = $this->lastResult['error_report_path'] ?? null;
        if (! $path) {
            return null;
        }

        $fullPath = storage_path('app/private/'.$path);
        abort_unless(is_file($fullPath), 404);

        return response()->download($fullPath);
    }
}
