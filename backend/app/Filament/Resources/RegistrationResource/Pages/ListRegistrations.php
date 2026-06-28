<?php
// [IN]: RegistrationResource table, summary service, and race selector / RegistrationResource 表格、汇总服务与赛事选择器
// [OUT]: Filament registration list page with inline summary and Excel export / 带内联汇总与 Excel 导出的 Filament 报名列表页面
// [POS]: Backend admin registration index route / 后端后台报名索引路由
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\RegistrationResource\Pages;

use App\Exports\RegistrationMatrixExport;
use App\Filament\Resources\RegistrationResource;
use App\Models\Race;
use App\Services\RegistrationSummaryService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
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
            Action::make('exportExcel')
                ->label('导出 Excel')
                ->form([
                    Select::make('race_id')
                        ->label('赛事')
                        ->options(fn (): array => Race::query()->orderByDesc('registration_end_at')->pluck('name', 'id')->all())
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data) {
                    $export = new RegistrationMatrixExport((int) $data['race_id']);

                    return Excel::download($export, $export->fileName());
                }),
        ];
    }
}
