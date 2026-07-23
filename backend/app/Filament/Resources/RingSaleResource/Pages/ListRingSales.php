<?php

// [IN]: Ring-sale quick-entry and date-range export requests / 售环快速录入与日期范围导出请求
// [OUT]: Full-screen create modal and three-sheet Excel download / 全屏新增弹层与三工作表 Excel 下载
// [POS]: Ring-sale Filament list page / 售环记录 Filament 列表页
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\RingSaleResource\Pages;

use App\Exports\RingSaleExport;
use App\Filament\Resources\RingSaleResource;
use App\Models\User;
use App\Services\RingSaleService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class ListRingSales extends ListRecords
{
    protected static string $resource = RingSaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createSale')
                ->label('新增售环')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => RingSaleResource::hasModulePermission('create'))
                ->schema(fn (): array => RingSaleResource::saleFormComponents(true))
                ->modalHeading('新增售环记录')
                ->modalDescription('选择会员可自动带出姓名和棚号；也可以直接记录非会员购买人。')
                ->modalSubmitActionLabel('保存售环单')
                ->stickyModalHeader()
                ->stickyModalFooter()
                ->modalWidth(Width::ScreenExtraLarge)
                ->extraModalWindowAttributes(['class' => 'ring-sale-entry-modal'])
                ->action(function (array $data): void {
                    abort_unless(RingSaleResource::hasModulePermission('create'), 403);
                    $admin = auth()->user();
                    abort_unless($admin instanceof User, 403);

                    $sale = app(RingSaleService::class)->create(
                        RingSaleResource::normalizeActionData($data, true),
                        $admin,
                    );

                    Notification::make()
                        ->title("已新增 {$sale->sale_no}")
                        ->body("共 {$sale->total_quantity} 枚，应收 ".RingSaleResource::formatYuan($sale->total_amount_cent).'。')
                        ->success()
                        ->send();
                }),
            Action::make('exportExcel')
                ->label('导出 Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (): bool => RingSaleResource::hasModulePermission('view'))
                ->schema([
                    DatePicker::make('start_date')
                        ->label('开始日期')
                        ->default(today()->startOfMonth())
                        ->maxDate(today())
                        ->required(),
                    DatePicker::make('end_date')
                        ->label('结束日期')
                        ->default(today())
                        ->maxDate(today())
                        ->required(),
                    Toggle::make('include_voided')
                        ->label('包含作废售环单')
                        ->default(false),
                ])
                ->modalHeading('按售环日期导出')
                ->modalSubmitActionLabel('导出 Excel')
                ->modalWidth(Width::Medium)
                ->action(function (array $data) {
                    abort_unless(RingSaleResource::hasModulePermission('view'), 403);
                    if ($data['end_date'] < $data['start_date']) {
                        throw ValidationException::withMessages([
                            'end_date' => '结束日期不能早于开始日期。',
                        ]);
                    }

                    $filename = "售环记录_{$data['start_date']}_至_{$data['end_date']}.xlsx";

                    return Excel::download(new RingSaleExport(
                        $data['start_date'],
                        $data['end_date'],
                        (bool) ($data['include_voided'] ?? false),
                    ), $filename);
                }),
        ];
    }
}
