<?php

// [IN]: RegistrationResource table, summary service, race selector, manual refresh, and delete-all action / RegistrationResource 表格、汇总服务、赛事选择器、手动刷新与全部删除动作
// [OUT]: Filament registration list page with inline summary, manual refresh, Excel export, and registration cleanup / 带内联汇总、手动刷新、Excel 导出与报名清理的 Filament 报名列表页面
// [POS]: Backend admin registration index route / 后端后台报名索引路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\RegistrationResource\Pages;

use App\Exports\RegistrationMatrixExport;
use App\Filament\Resources\RegistrationResource;
use App\Models\Race;
use App\Models\Registration;
use App\Services\RegistrationSummaryService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Maatwebsite\Excel\Facades\Excel;

class ListRegistrations extends ListRecords
{
    protected static string $resource = RegistrationResource::class;

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                View::make('filament.resources.registration-resource.registration-summary')
                    ->viewData(fn (): array => [
                        'summary' => app(RegistrationSummaryService::class)->totals(),
                    ]),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshRegistrations')
                ->label('刷新报名记录')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => RegistrationResource::hasModulePermission('view'))
                ->action(function (): void {
                    abort_unless(RegistrationResource::hasModulePermission('view'), 403);

                    $this->flushCachedTableRecords();

                    Notification::make()
                        ->title('报名记录已刷新')
                        ->success()
                        ->send();
                }),
            Action::make('exportExcel')
                ->label('导出 Excel')
                ->visible(fn (): bool => RegistrationResource::hasModulePermission('view'))
                ->form([
                    Select::make('race_id')
                        ->label('赛事')
                        ->options(fn (): array => Race::query()->orderByDesc('registration_end_at')->pluck('name', 'id')->all())
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data) {
                    abort_unless(RegistrationResource::hasModulePermission('view'), 403);
                    $export = new RegistrationMatrixExport((int) $data['race_id']);

                    return Excel::download($export, $export->fileName());
                }),
            Action::make('deleteAllRegistrations')
                ->label('删除所有报名记录')
                ->visible(fn (): bool => RegistrationResource::hasModulePermission('delete'))
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modalHeading('删除所有报名记录')
                ->modalDescription('此操作会删除全部报名记录、普通报名明细和递进报名明细。删除后会员端不再恢复这些报名。')
                ->modalSubmitActionLabel('确认删除')
                ->action(fn () => $this->deleteAllRegistrations()),
        ];
    }

    private function deleteAllRegistrations(): void
    {
        abort_unless(RegistrationResource::hasModulePermission('delete'), 403);

        $deleted = RegistrationResource::deleteRegistrations(Registration::query()->get());

        Notification::make()
            ->title("已删除 {$deleted} 条报名记录")
            ->success()
            ->send();
    }
}
