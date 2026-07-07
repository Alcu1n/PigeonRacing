<?php
// [IN]: Uploaded Excel file, progressive category record, and preview token / 已上传 Excel、递进类别记录与预览令牌
// [OUT]: First-stage baseline import page / 第一阶段基准导入页
// [POS]: Backend admin progressive first-stage import route / 后端后台递进第一阶段导入路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\RegistrationCategoryResource\Pages;

use App\Exports\ProgressiveStageImportTemplateExport;
use App\Filament\Resources\RegistrationCategoryResource;
use App\Models\RegistrationCategory;
use App\Services\ProgressiveStageImportService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportFirstStage extends Page
{
    use InteractsWithRecord;
    use WithFileUploads;

    protected static string $resource = RegistrationCategoryResource::class;

    protected string $view = 'filament.resources.registration-category-resource.pages.import-first-stage';

    public ?TemporaryUploadedFile $upload = null;

    public ?array $preview = null;

    public ?string $fileName = null;

    public ?string $previewToken = null;

    public ?array $lastResult = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->category()->load(['race', 'stageProjects']);
    }

    public function getTitle(): string
    {
        return "导入 {$this->category()->name} 第一阶段";
    }

    public function previewUpload(ProgressiveStageImportService $service): void
    {
        $this->validate([
            'upload' => ['required', 'file', 'mimes:xlsx,xls', 'max:'.ProgressiveStageImportService::MAX_UPLOAD_KB],
        ]);

        try {
            $rows = $service->rowsFromSpreadsheet($this->upload->getRealPath(), $this->category());
            $preview = $service->preview($rows);
            $this->previewToken = $service->storeRowsForConfirmation($rows);
            $this->preview = $service->browserPreview([...$preview, 'token' => $this->previewToken]);
            $this->fileName = $this->upload->getClientOriginalName();
            $this->lastResult = null;
        } catch (ValidationException $exception) {
            throw $exception;
        }
    }

    public function confirmImport(ProgressiveStageImportService $service): void
    {
        if (! $this->preview || ! $this->fileName || ! $this->previewToken) {
            return;
        }

        $batch = $service->commitStoredPreview($this->category(), $this->fileName, $this->previewToken, auth()->id());
        $this->lastResult = [
            'success_rows' => $batch->success_rows,
            'failed_rows' => $batch->failed_rows,
            'error_report_path' => $batch->error_report_path,
        ];

        Notification::make()
            ->title("导入完成：成功 {$batch->success_rows} 行，失败 {$batch->failed_rows} 行")
            ->success()
            ->send();

        $this->reset(['upload', 'preview', 'fileName', 'previewToken']);
    }

    public function resetImport(ProgressiveStageImportService $service): void
    {
        $service->discardStoredPreview($this->previewToken);
        $this->reset(['upload', 'preview', 'fileName', 'previewToken', 'lastResult']);
    }

    public function downloadTemplate(ProgressiveStageImportService $service): BinaryFileResponse
    {
        $stage = $service->firstStage($this->category());

        return Excel::download(new ProgressiveStageImportTemplateExport($stage->name), "递进第一阶段导入模板-{$this->category()->name}.xlsx");
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

    private function category(): RegistrationCategory
    {
        $record = $this->getRecord();
        abort_unless($record instanceof RegistrationCategory, 404);

        return $record;
    }
}
