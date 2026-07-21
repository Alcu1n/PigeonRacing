<?php

// [IN]: Uploaded Excel file, server-side preview token, and pigeon import service / 已上传 Excel 文件、服务端预览令牌与足环导入服务
// [OUT]: Large-file-safe preview-confirm pigeon import page / 大文件安全的预览确认式足环导入页面
// [POS]: Backend admin pigeon import route / 后端后台足环导入路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\PigeonResource\Pages;

use App\Filament\Resources\PigeonResource;
use App\Models\PigeonLibrary;
use App\Services\PigeonImportService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportPigeons extends Page
{
    use WithFileUploads;

    protected static string $resource = PigeonResource::class;

    protected string $view = 'filament.resources.pigeon-resource.pages.import-pigeons';

    public ?TemporaryUploadedFile $upload = null;

    public ?array $preview = null;

    public ?string $fileName = null;

    public ?string $previewToken = null;

    public ?array $lastResult = null;

    public ?int $pigeonLibraryId = null;

    public static function canAccess(array $parameters = []): bool
    {
        return PigeonResource::hasModulePermission('create');
    }

    public function mount(): void
    {
        $this->pigeonLibraryId = PigeonLibrary::default()->id;
    }

    public function getTitle(): string
    {
        return '导入足环 Excel';
    }

    public function previewUpload(PigeonImportService $service): void
    {
        $this->validate([
            'pigeonLibraryId' => ['required', 'integer', 'exists:pigeon_libraries,id'],
            'upload' => ['required', 'file', 'mimes:xlsx,xls', 'max:'.PigeonImportService::MAX_UPLOAD_KB],
        ]);

        try {
            $library = PigeonLibrary::query()->findOrFail($this->pigeonLibraryId);
            $rows = $service->rowsFromSpreadsheet($this->upload->getRealPath());
            $preview = $service->preview($rows, $library);
            $this->previewToken = $service->storeRowsForConfirmation($rows);
            $this->preview = $service->browserPreview([...$preview, 'token' => $this->previewToken]);
            $this->fileName = $this->upload->getClientOriginalName();
            $this->lastResult = null;
        } catch (ValidationException $exception) {
            throw $exception;
        }
    }

    public function confirmImport(PigeonImportService $service): void
    {
        if (! $this->preview || ! $this->fileName || ! $this->previewToken) {
            return;
        }

        $library = PigeonLibrary::query()->findOrFail($this->pigeonLibraryId);
        $batch = $service->commitStoredPreview($this->fileName, $this->previewToken, auth()->id(), $library);
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

    public function libraryOptions(): array
    {
        return PigeonLibrary::query()
            ->orderByDesc('is_enabled')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function resetImport(PigeonImportService $service): void
    {
        $service->discardStoredPreview($this->previewToken);
        $this->reset(['upload', 'preview', 'fileName', 'previewToken', 'lastResult']);
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
