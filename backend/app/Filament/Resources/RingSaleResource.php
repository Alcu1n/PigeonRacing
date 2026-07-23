<?php

// [IN]: Ring-sale aggregate, quick-entry form state, payments, and filtered list query / 售环聚合、快速录入表单状态、收款与筛选查询
// [OUT]: Compact mobile-first modal workflow, single-line ledger, payment actions, filters, and details / 紧凑移动优先弹层、单行台账、收款动作、筛选与详情
// [POS]: Ring-sale Filament resource / 售环记录 Filament 资源
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources;

use App\Filament\Clusters\RingSales;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Resources\RingSaleResource\Pages;
use App\Models\AdminLog;
use App\Models\Member;
use App\Models\RingNumberPrefix;
use App\Models\RingSale;
use App\Models\RingSaleCategory;
use App\Models\RingSalePayment;
use App\Models\RingSaleReceipt;
use App\Models\User;
use App\Services\RingSaleService;
use App\Services\RingSaleSummaryService;
use App\Support\RingNumberRange;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RingSaleResource extends Resource
{
    use HasModulePermissions;

    protected static string $permissionModule = 'ring-sales';

    protected static ?string $cluster = RingSales::class;

    protected static ?string $model = RingSale::class;

    protected static ?string $slug = 'records';

    protected static ?string $navigationLabel = '售环记录';

    protected static ?string $modelLabel = '售环记录';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        $paidSql = self::paidAmountSql();

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withFinancials()
                ->with(['items', 'payments', 'receipts', 'creator']))
            ->defaultSort('sale_date', 'desc')
            ->defaultPaginationPageOption(50)
            ->searchable([
                fn (Builder $query, string $search): Builder => $query->containingRing($search),
            ])
            ->searchPlaceholder('搜索单号、姓名、棚号或足环号码')
            ->header(fn ($livewire) => view('filament.resources.ring-sale-resource.summary', [
                'summary' => app(RingSaleSummaryService::class)->summarize(
                    $livewire->getFilteredTableQuery(),
                ),
            ]))
            ->columns([
                TextColumn::make('payment_status')
                    ->label('付款')
                    ->state(fn (RingSale $record): string => $record->payment_status_label)
                    ->badge()
                    ->color(fn (RingSale $record): string => $record->payment_status_color)
                    ->extraAttributes(['class' => 'ring-sale-nowrap']),
                TextColumn::make('buyer_name')
                    ->label('姓名')
                    ->searchable()
                    ->extraAttributes(['class' => 'ring-sale-nowrap']),
                TextColumn::make('loft_number')
                    ->label('棚号')
                    ->searchable()
                    ->placeholder('—')
                    ->extraAttributes(['class' => 'ring-sale-nowrap']),
                TextColumn::make('total_amount_cent')
                    ->label('总金额')
                    ->formatStateUsing(fn (int $state): string => self::formatYuan($state))
                    ->sortable()
                    ->extraAttributes(['class' => 'ring-sale-nowrap']),
                TextColumn::make('paid_amount_cent')
                    ->label('已付')
                    ->state(fn (RingSale $record): int => $record->paid_amount_cent)
                    ->formatStateUsing(fn (int $state): string => self::formatYuan($state))
                    ->extraAttributes(['class' => 'ring-sale-nowrap']),
                TextColumn::make('unpaid_amount_cent')
                    ->label('未付')
                    ->state(fn (RingSale $record): int => $record->unpaid_amount_cent)
                    ->formatStateUsing(fn (int $state): string => self::formatYuan($state))
                    ->color(fn (RingSale $record): string => $record->unpaid_amount_cent > 0 ? 'danger' : 'success')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderByRaw("(ring_sales.total_amount_cent - ({$paidSql})) {$direction}"))
                    ->extraAttributes(['class' => 'ring-sale-nowrap']),
                TextColumn::make('items_summary')
                    ->label('号码段')
                    ->state(fn (RingSale $record): string => self::itemsSummary($record))
                    ->extraAttributes([
                        'class' => 'ring-sale-segments-scroll',
                        'tabindex' => '0',
                    ]),
                TextColumn::make('total_quantity')
                    ->label('数量')
                    ->sortable()
                    ->extraAttributes(['class' => 'ring-sale-nowrap']),
                TextColumn::make('sale_no')
                    ->label('售环单号')
                    ->searchable()
                    ->copyable()
                    ->extraAttributes(['class' => 'ring-sale-nowrap']),
                TextColumn::make('sale_date')
                    ->label('售环日期')
                    ->date()
                    ->sortable()
                    ->extraAttributes(['class' => 'ring-sale-nowrap']),
            ])
            ->filters([
                Filter::make('sale_date')
                    ->label('售环日期')
                    ->schema([
                        DatePicker::make('from')->label('开始日期')->maxDate(today()),
                        DatePicker::make('until')->label('结束日期')->maxDate(today()),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('sale_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('sale_date', '<=', $date))),
                SelectFilter::make('payment_status')
                    ->label('付款状态')
                    ->options([
                        'paid' => '付清',
                        'partial' => '部分付款',
                        'unpaid' => '未付款',
                    ])
                    ->query(function (Builder $query, array $data) use ($paidSql): Builder {
                        return match ($data['value'] ?? null) {
                            'paid' => $query->where('status', 'active')->whereRaw("({$paidSql}) = ring_sales.total_amount_cent"),
                            'partial' => $query->where('status', 'active')
                                ->whereRaw("({$paidSql}) > 0")
                                ->whereRaw("({$paidSql}) < ring_sales.total_amount_cent"),
                            'unpaid' => $query->where('status', 'active')->whereRaw("({$paidSql}) = 0"),
                            default => $query,
                        };
                    }),
                SelectFilter::make('category_id')
                    ->label('足环类别')
                    ->options(fn (): array => RingSaleCategory::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $categoryId): Builder => $query->whereHas(
                            'items',
                            fn (Builder $items): Builder => $items->where('ring_sale_category_id', $categoryId),
                        ),
                    )),
                SelectFilter::make('status')
                    ->label('记录状态')
                    ->options(['active' => '有效', 'void' => '作废'])
                    ->default('active'),
            ])
            ->recordActions([
                ActionGroup::make([
                    self::viewAction(),
                    self::editAction(),
                    self::addPaymentAction(),
                    self::editPaymentAction(),
                    self::voidPaymentAction(),
                    self::voidSaleAction(),
                ]),
            ]);
    }

    /** @return array<int, mixed> */
    public static function saleFormComponents(bool $includeInitialPayment, int $existingPaidAmountCent = 0): array
    {
        return [
            Section::make('购买人')
                ->compact()
                ->schema([
                    Grid::make(2)
                        ->extraAttributes(['class' => 'ring-sale-paired-grid'])
                        ->schema([
                            Select::make('member_id')
                                ->label('关联会员（可选）')
                                ->relationship('member', 'loft_number')
                                ->getOptionLabelFromRecordUsing(fn (Member $record): string => "{$record->loft_number} · {$record->participant_name}")
                                ->searchable(['loft_number', 'participant_name', 'phone'])
                                ->preload()
                                ->live()
                                ->afterStateUpdated(function (Set $set, ?int $state): void {
                                    $member = $state ? Member::query()->find($state) : null;
                                    if ($member) {
                                        $set('buyer_name', $member->participant_name);
                                        $set('loft_number', $member->loft_number);
                                    }
                                }),
                            DatePicker::make('sale_date')
                                ->label('售环日期')
                                ->default(today())
                                ->maxDate(today())
                                ->required(),
                        ]),
                    Grid::make(2)
                        ->extraAttributes(['class' => 'ring-sale-paired-grid'])
                        ->schema([
                            TextInput::make('buyer_name')
                                ->label('姓名')
                                ->required()
                                ->maxLength(100),
                            TextInput::make('loft_number')
                                ->label('棚号（可选）')
                                ->maxLength(100),
                        ]),
                ]),
            Section::make('号码段明细')
                ->compact()
                ->schema([
                    Repeater::make('items')
                        ->hiddenLabel()
                        ->schema([
                            Grid::make(2)
                                ->extraAttributes(['class' => 'ring-sale-paired-grid'])
                                ->schema([
                                    Select::make('category_id')
                                        ->label('足环类别')
                                        ->options(fn (Get $get): array => RingSaleCategory::query()
                                            ->where(function (Builder $query) use ($get): void {
                                                $query->where('is_enabled', true);
                                                if ($get('category_id')) {
                                                    $query->orWhere('id', $get('category_id'));
                                                }
                                            })
                                            ->orderBy('name')
                                            ->get()
                                            ->mapWithKeys(fn (RingSaleCategory $category): array => [
                                                $category->id => "{$category->name} · ".self::formatYuan($category->unit_price_cent).'/枚'
                                                    .($category->is_enabled ? '' : '（已停用）'),
                                            ])
                                            ->all())
                                        ->searchable()
                                        ->required()
                                        ->live(),
                                    ToggleButtons::make('entry_mode')
                                        ->label('录入方式')
                                        ->options(['prefix' => '前缀＋尾号', 'full' => '完整号码'])
                                        ->default('prefix')
                                        ->inline()
                                        ->required()
                                        ->live(),
                                ]),
                            Select::make('prefix_id')
                                ->label('号码前缀')
                                ->options(fn (Get $get): array => RingNumberPrefix::query()
                                    ->where(function (Builder $query) use ($get): void {
                                        $query->where('is_enabled', true);
                                        if ($get('prefix_id')) {
                                            $query->orWhere('id', $get('prefix_id'));
                                        }
                                    })
                                    ->orderBy('id')
                                    ->get()
                                    ->mapWithKeys(fn (RingNumberPrefix $prefix): array => [
                                        $prefix->id => "{$prefix->prefix}（{$prefix->suffix_width} 位尾号）"
                                            .($prefix->is_enabled ? '' : '（已停用）'),
                                    ])
                                    ->all())
                                ->searchable()
                                ->required(fn (Get $get): bool => $get('entry_mode') !== 'full')
                                ->visible(fn (Get $get): bool => $get('entry_mode') !== 'full')
                                ->live()
                                ->columnSpanFull(),
                            Grid::make(2)
                                ->extraAttributes(['class' => 'ring-sale-paired-grid'])
                                ->visible(fn (Get $get): bool => $get('entry_mode') !== 'full')
                                ->schema([
                                    TextInput::make('start_suffix')
                                        ->label('起始尾号')
                                        ->inputMode('numeric')
                                        ->placeholder('0001')
                                        ->required(fn (Get $get): bool => $get('entry_mode') !== 'full')
                                        ->live(onBlur: true),
                                    TextInput::make('end_suffix')
                                        ->label('结束尾号')
                                        ->inputMode('numeric')
                                        ->placeholder('0020')
                                        ->required(fn (Get $get): bool => $get('entry_mode') !== 'full')
                                        ->live(onBlur: true),
                                ]),
                            Grid::make(2)
                                ->extraAttributes(['class' => 'ring-sale-paired-grid'])
                                ->visible(fn (Get $get): bool => $get('entry_mode') === 'full')
                                ->schema([
                                    TextInput::make('start_ring')
                                        ->label('完整起始号')
                                        ->required(fn (Get $get): bool => $get('entry_mode') === 'full')
                                        ->live(onBlur: true),
                                    TextInput::make('end_ring')
                                        ->label('完整结束号')
                                        ->required(fn (Get $get): bool => $get('entry_mode') === 'full')
                                        ->live(onBlur: true),
                                ]),
                            Placeholder::make('item_summary_preview')
                                ->hiddenLabel()
                                ->content(fn (Get $get): string => self::itemSummaryPreview($get))
                                ->extraAttributes([
                                    'class' => 'ring-sale-item-summary',
                                    'tabindex' => '0',
                                ]),
                        ])
                        ->defaultItems(1)
                        ->minItems(1)
                        ->cloneable()
                        ->reorderable(false)
                        ->addActionLabel('新增号码段')
                        ->itemLabel(fn (array $state): ?string => filled($state['start_ring'] ?? null)
                            ? ($state['start_ring'].' – '.($state['end_ring'] ?? ''))
                            : null)
                        ->columnSpanFull(),
                ]),
            Section::make('金额与凭证')
                ->compact()
                ->schema([
                    Grid::make(['default' => 2, 'md' => 4])->schema([
                        Placeholder::make('total_quantity_preview')
                            ->label('足环总数')
                            ->content(fn (Get $get): string => (string) self::formSummary($get, $existingPaidAmountCent)['quantity']),
                        Placeholder::make('total_amount_preview')
                            ->label('总金额')
                            ->content(fn (Get $get): string => self::formatYuan(self::formSummary($get, $existingPaidAmountCent)['total_amount_cent'])),
                        TextInput::make('initial_paid_amount_cent')
                            ->label('首付款')
                            ->prefix('¥')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->default(0)
                            ->visible($includeInitialPayment)
                            ->required($includeInitialPayment)
                            ->live(onBlur: true),
                        DatePicker::make('initial_payment_date')
                            ->label('首付款日期')
                            ->default(today())
                            ->maxDate(today())
                            ->visible($includeInitialPayment),
                        Placeholder::make('unpaid_amount_preview')
                            ->label('未付金额')
                            ->content(fn (Get $get): string => self::formatYuan(self::formSummary($get, $existingPaidAmountCent)['unpaid_amount_cent'])),
                    ]),
                    TextInput::make('initial_payment_remark')
                        ->label('首付款备注')
                        ->visible($includeInitialPayment)
                        ->maxLength(255),
                    Textarea::make('remark')
                        ->label('备注')
                        ->rows(2)
                        ->columnSpanFull(),
                    FileUpload::make('receipt_paths')
                        ->label('收据照片')
                        ->helperText('最多 3 张，每张不超过 10 MB；手机可直接调用后置摄像头。')
                        ->disk('local')
                        ->directory('ring-sale-receipts')
                        ->visibility('private')
                        ->image()
                        ->multiple()
                        ->maxFiles(3)
                        ->maxSize(10240)
                        ->acceptedFileTypes([
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'image/heic',
                            'image/heif',
                        ])
                        ->extraInputAttributes(['capture' => 'environment'])
                        ->getUploadedFileUsing(function (string $file): ?array {
                            $receipt = RingSaleReceipt::query()->where('path', $file)->first();
                            if (! $receipt || ! Storage::disk($receipt->disk)->exists($receipt->path)) {
                                return null;
                            }

                            return [
                                'name' => $receipt->original_name,
                                'size' => (int) ($receipt->size ?? 0),
                                'type' => $receipt->mime_type ?? 'application/octet-stream',
                                'url' => route('admin.ring-sale-receipts.show', $receipt),
                            ];
                        })
                        ->columnSpanFull(),
                ]),
        ];
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function normalizeActionData(array $data, bool $includeInitialPayment): array
    {
        if ($includeInitialPayment) {
            $data['initial_paid_amount_cent'] = self::yuanInputToCent($data['initial_paid_amount_cent'] ?? 0);
            $data['initial_payment_date'] ??= $data['sale_date'] ?? today()->toDateString();
        } else {
            unset($data['initial_paid_amount_cent'], $data['initial_payment_date'], $data['initial_payment_remark']);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    public static function formData(RingSale $sale): array
    {
        $sale->loadMissing(['items', 'receipts']);

        return [
            'member_id' => $sale->member_id,
            'buyer_name' => $sale->buyer_name,
            'loft_number' => $sale->loft_number,
            'sale_date' => $sale->sale_date->toDateString(),
            'remark' => $sale->remark,
            'receipt_paths' => $sale->receipts->pluck('path')->all(),
            'items' => $sale->items->map(fn ($item): array => [
                'category_id' => $item->ring_sale_category_id,
                'entry_mode' => $item->entry_mode,
                'prefix_id' => $item->ring_number_prefix_id,
                'start_suffix' => $item->entry_mode === 'prefix' ? (string) $item->start_number : null,
                'end_suffix' => $item->entry_mode === 'prefix' ? (string) $item->end_number : null,
                'start_ring' => $item->entry_mode === 'full' ? $item->start_ring : null,
                'end_ring' => $item->entry_mode === 'full' ? $item->end_ring : null,
            ])->all(),
        ];
    }

    private static function viewAction(): Action
    {
        return Action::make('viewSale')
            ->label('查看详情')
            ->icon('heroicon-o-eye')
            ->modalHeading(fn (RingSale $record): string => "售环单 {$record->sale_no}")
            ->modalContent(fn (RingSale $record): View => self::detailView($record))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('关闭')
            ->modalWidth(Width::FiveExtraLarge)
            ->slideOver();
    }

    private static function editAction(): Action
    {
        return Action::make('editSale')
            ->label('编辑售环单')
            ->icon('heroicon-o-pencil-square')
            ->visible(fn (RingSale $record): bool => self::hasModulePermission('update') && $record->status === 'active')
            ->fillForm(fn (RingSale $record): array => self::formData($record))
            ->schema(fn (RingSale $record): array => self::saleFormComponents(false, $record->paid_amount_cent))
            ->modalHeading(fn (RingSale $record): string => "编辑 {$record->sale_no}")
            ->modalSubmitActionLabel('保存修改')
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth(Width::ScreenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'ring-sale-entry-modal'])
            ->action(function (array $data, RingSale $record): void {
                abort_unless(self::hasModulePermission('update'), 403);
                app(RingSaleService::class)->update(
                    $record,
                    self::normalizeActionData($data, false),
                    self::admin(),
                );
                Notification::make()->title('售环单已更新')->success()->send();
            });
    }

    private static function addPaymentAction(): Action
    {
        return Action::make('addPayment')
            ->label('登记收款')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (RingSale $record): bool => self::hasModulePermission('update')
                && $record->status === 'active'
                && $record->unpaid_amount_cent > 0)
            ->schema([
                DatePicker::make('payment_date')->label('收款日期')->default(today())->maxDate(today())->required(),
                TextInput::make('amount_cent')->label('收款金额')->prefix('¥')->numeric()->step(0.01)->minValue(0.01)->required(),
                Textarea::make('remark')->label('备注')->rows(2),
            ])
            ->modalHeading(fn (RingSale $record): string => '登记收款 · 尚欠 '.self::formatYuan($record->unpaid_amount_cent))
            ->modalSubmitActionLabel('确认收款')
            ->action(function (array $data, RingSale $record): void {
                abort_unless(self::hasModulePermission('update'), 403);
                $data['amount_cent'] = self::yuanInputToCent($data['amount_cent']);
                app(RingSaleService::class)->addPayment($record, $data, self::admin());
                Notification::make()->title('收款已登记')->success()->send();
            });
    }

    private static function editPaymentAction(): Action
    {
        return Action::make('editPayment')
            ->label('修改收款')
            ->icon('heroicon-o-pencil')
            ->visible(fn (RingSale $record): bool => self::hasModulePermission('update')
                && $record->status === 'active'
                && $record->payments()->where('status', 'active')->exists())
            ->schema([
                Select::make('payment_id')
                    ->label('选择收款流水')
                    ->options(fn (RingSale $record): array => $record->payments()
                        ->where('status', 'active')
                        ->orderBy('payment_date')
                        ->get()
                        ->mapWithKeys(fn (RingSalePayment $payment): array => [
                            $payment->id => $payment->payment_date->format('Y-m-d').' · '.self::formatYuan($payment->amount_cent),
                        ])
                        ->all())
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, ?int $state): void {
                        $payment = $state ? RingSalePayment::query()->find($state) : null;
                        $set('payment_date', $payment?->payment_date?->toDateString());
                        $set('amount_cent', $payment ? $payment->amount_cent / 100 : null);
                        $set('remark', $payment?->remark);
                    }),
                DatePicker::make('payment_date')->label('收款日期')->maxDate(today())->required(),
                TextInput::make('amount_cent')->label('收款金额')->prefix('¥')->numeric()->step(0.01)->minValue(0.01)->required(),
                Textarea::make('remark')->label('备注')->rows(2),
            ])
            ->modalHeading('修改收款流水')
            ->modalSubmitActionLabel('保存修改')
            ->action(function (array $data, RingSale $record): void {
                abort_unless(self::hasModulePermission('update'), 403);
                $payment = $record->payments()->whereKey($data['payment_id'])->firstOrFail();
                $data['amount_cent'] = self::yuanInputToCent($data['amount_cent']);
                app(RingSaleService::class)->updatePayment($payment, $data, self::admin());
                Notification::make()->title('收款流水已更新')->success()->send();
            });
    }

    private static function voidPaymentAction(): Action
    {
        return Action::make('voidPayment')
            ->label('作废收款')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (RingSale $record): bool => self::hasModulePermission('delete')
                && $record->payments()->where('status', 'active')->exists())
            ->schema([
                Select::make('payment_id')
                    ->label('选择收款流水')
                    ->options(fn (RingSale $record): array => $record->payments()
                        ->where('status', 'active')
                        ->orderBy('payment_date')
                        ->get()
                        ->mapWithKeys(fn (RingSalePayment $payment): array => [
                            $payment->id => $payment->payment_date->format('Y-m-d').' · '.self::formatYuan($payment->amount_cent),
                        ])
                        ->all())
                    ->required(),
                Textarea::make('void_reason')->label('作废原因')->required()->rows(2),
            ])
            ->requiresConfirmation()
            ->modalHeading('作废收款流水')
            ->modalSubmitActionLabel('确认作废')
            ->action(function (array $data, RingSale $record): void {
                abort_unless(self::hasModulePermission('delete'), 403);
                $payment = $record->payments()->whereKey($data['payment_id'])->firstOrFail();
                app(RingSaleService::class)->voidPayment($payment, $data['void_reason'], self::admin());
                Notification::make()->title('收款流水已作废')->success()->send();
            });
    }

    private static function voidSaleAction(): Action
    {
        return Action::make('voidSale')
            ->label('作废售环单')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->visible(fn (RingSale $record): bool => self::hasModulePermission('delete') && $record->status === 'active')
            ->schema([
                Textarea::make('void_reason')->label('作废原因')->required()->rows(3),
            ])
            ->requiresConfirmation()
            ->modalHeading(fn (RingSale $record): string => "作废 {$record->sale_no}")
            ->modalDescription('作废后保留历史明细、收款和收据，并释放全部号码；此操作不能恢复。')
            ->modalSubmitActionLabel('确认作废')
            ->action(function (array $data, RingSale $record): void {
                abort_unless(self::hasModulePermission('delete'), 403);
                app(RingSaleService::class)->voidSale($record, $data['void_reason'], self::admin());
                Notification::make()->title('售环单已作废')->success()->send();
            });
    }

    /** @return array{quantity: int, total_amount_cent: int, unpaid_amount_cent: int} */
    private static function formSummary(Get $get, int $existingPaidAmountCent = 0): array
    {
        $quantity = 0;
        $total = 0;

        foreach ((array) $get('items') as $item) {
            try {
                $range = self::rangeFromState((array) $item);
                $category = RingSaleCategory::query()->find($item['category_id'] ?? null);
                if (! $range || ! $category) {
                    continue;
                }

                $quantity += $range->quantity();
                $total += $range->quantity() * (int) $category->unit_price_cent;
            } catch (Throwable) {
                continue;
            }
        }

        $paid = $existingPaidAmountCent + self::yuanInputToCent($get('initial_paid_amount_cent') ?? 0);

        return [
            'quantity' => $quantity,
            'total_amount_cent' => $total,
            'unpaid_amount_cent' => max(0, $total - $paid),
        ];
    }

    /** @return array{quantity?: int, unit_price?: string, line_amount?: string} */
    private static function itemMetrics(Get $get): array
    {
        try {
            $range = self::rangeFromGet($get);
            $category = RingSaleCategory::query()->find($get('category_id'));
            if (! $range || ! $category) {
                return [];
            }

            return [
                'quantity' => $range->quantity(),
                'unit_price' => self::formatYuan($category->unit_price_cent),
                'line_amount' => self::formatYuan($range->quantity() * $category->unit_price_cent),
            ];
        } catch (Throwable) {
            return [];
        }
    }

    private static function itemPreview(Get $get): string
    {
        try {
            $range = self::rangeFromGet($get);

            return $range ? "{$range->startRing} – {$range->endRing}" : '请完成号码段';
        } catch (Throwable $exception) {
            return $exception->getMessage();
        }
    }

    private static function itemSummaryPreview(Get $get): string
    {
        $range = self::itemPreview($get);
        $metrics = self::itemMetrics($get);
        if ($metrics === []) {
            return $range;
        }

        return "{$range} · {$metrics['quantity']} 枚 · {$metrics['unit_price']}/枚 · {$metrics['line_amount']}";
    }

    private static function rangeFromGet(Get $get): ?RingNumberRange
    {
        return self::rangeFromState([
            'entry_mode' => $get('entry_mode'),
            'prefix_id' => $get('prefix_id'),
            'start_suffix' => $get('start_suffix'),
            'end_suffix' => $get('end_suffix'),
            'start_ring' => $get('start_ring'),
            'end_ring' => $get('end_ring'),
        ]);
    }

    /** @param array<string, mixed> $state */
    private static function rangeFromState(array $state): ?RingNumberRange
    {
        if (($state['entry_mode'] ?? 'prefix') === 'full') {
            if (blank($state['start_ring'] ?? null) || blank($state['end_ring'] ?? null)) {
                return null;
            }

            return RingNumberRange::fromFull((string) $state['start_ring'], (string) $state['end_ring']);
        }

        $prefix = RingNumberPrefix::query()->find($state['prefix_id'] ?? null);
        if (! $prefix || blank($state['start_suffix'] ?? null) || blank($state['end_suffix'] ?? null)) {
            return null;
        }

        return RingNumberRange::fromPrefix(
            $prefix->prefix,
            $prefix->suffix_width,
            (string) $state['start_suffix'],
            (string) $state['end_suffix'],
        );
    }

    private static function itemsSummary(RingSale $sale): string
    {
        return $sale->items
            ->map(fn ($item): string => "{$item->category_name_snapshot}：{$item->start_ring}–{$item->end_ring}")
            ->implode('；');
    }

    private static function detailView(RingSale $sale): View
    {
        $sale->load(['items', 'payments.creator', 'receipts', 'creator', 'voider']);
        $paymentIds = $sale->payments->modelKeys();
        $logs = AdminLog::query()
            ->with('admin')
            ->where(function (Builder $query) use ($sale, $paymentIds): void {
                $query->where(function (Builder $query) use ($sale): void {
                    $query->where('target_type', RingSale::class)
                        ->where('target_id', $sale->id);
                })->when($paymentIds !== [], fn (Builder $query): Builder => $query->orWhere(function (Builder $query) use ($paymentIds): void {
                    $query->where('target_type', RingSalePayment::class)
                        ->whereIn('target_id', $paymentIds);
                }));
            })
            ->latest('created_at')
            ->get();

        return view('filament.resources.ring-sale-resource.detail', [
            'sale' => $sale,
            'logs' => $logs,
        ]);
    }

    private static function paidAmountSql(): string
    {
        return "SELECT COALESCE(SUM(amount_cent), 0) FROM ring_sale_payments WHERE ring_sale_payments.ring_sale_id = ring_sales.id AND ring_sale_payments.status = 'active'";
    }

    public static function formatYuan(int $amountCent): string
    {
        return '¥'.number_format($amountCent / 100, 2);
    }

    private static function yuanInputToCent(mixed $amount): int
    {
        return max(0, (int) round(((float) $amount) * 100));
    }

    private static function admin(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRingSales::route('/'),
        ];
    }
}
