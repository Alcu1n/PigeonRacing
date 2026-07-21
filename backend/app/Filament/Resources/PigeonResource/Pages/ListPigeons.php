<?php

// [IN]: PigeonResource table definition, pigeons, registration references, and cache service / PigeonResource 表格定义、足环、报名引用与缓存服务
// [OUT]: Filament pigeon list page with import/export/template/delete-all actions / 带导入、导出、模板与全部删除动作的 Filament 足环列表页面
// [POS]: Backend admin pigeon index route / 后端后台足环索引路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\PigeonResource\Pages;

use App\Exports\PigeonExport;
use App\Exports\PigeonImportTemplateExport;
use App\Filament\Resources\PigeonResource;
use App\Models\Pigeon;
use App\Services\RaceCacheService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ListPigeons extends ListRecords
{
    protected static string $resource = PigeonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('importExcel')
                ->label('导入 Excel')
                ->visible(fn (): bool => PigeonResource::hasModulePermission('create'))
                ->url(PigeonResource::getUrl('import')),
            Action::make('downloadTemplate')
                ->label('下载模板')
                ->visible(fn (): bool => PigeonResource::hasModulePermission('create'))
                ->action(function () {
                    abort_unless(PigeonResource::hasModulePermission('create'), 403);

                    return Excel::download(new PigeonImportTemplateExport, '足环导入模板.xlsx');
                }),
            Action::make('exportExcel')
                ->label('导出 Excel')
                ->visible(fn (): bool => PigeonResource::hasModulePermission('view'))
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    abort_unless(PigeonResource::hasModulePermission('view'), 403);

                    return Excel::download(new PigeonExport, '足环列表.xlsx');
                }),
            Action::make('deleteAllPigeons')
                ->label('删除所有足环')
                ->visible(fn (): bool => PigeonResource::hasModulePermission('delete'))
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modalHeading('删除所有足环')
                ->modalDescription('此操作会删除全部未被报名记录引用的足环。若已有报名记录引用足环，系统会阻止删除以保护历史报名数据。')
                ->modalSubmitActionLabel('确认删除')
                ->action(fn () => $this->deleteAllPigeons()),
        ];
    }

    private function deleteAllPigeons(): void
    {
        abort_unless(PigeonResource::hasModulePermission('delete'), 403);

        $referenced = DB::table('registration_entry_pigeons')->count();

        if ($referenced > 0) {
            Notification::make()
                ->title('无法删除所有足环')
                ->body("已有 {$referenced} 条报名明细引用足环。请先处理报名记录，再删除足环。")
                ->danger()
                ->send();

            return;
        }

        $memberIds = Pigeon::query()
            ->whereNotNull('member_id')
            ->distinct()
            ->pluck('member_id');

        $deleted = DB::transaction(fn (): int => Pigeon::query()->delete());

        $memberIds
            ->each(fn (int $memberId) => app(RaceCacheService::class)->forgetMemberPigeonsById($memberId));

        Notification::make()
            ->title("已删除 {$deleted} 个足环")
            ->success()
            ->send();
    }
}
