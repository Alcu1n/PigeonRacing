<?php

// [IN]: Exact buyer-name ring-sale summary, receipt gallery, and scoped sale actions / 精确姓名售环汇总、收据画廊与限定售环操作
// [OUT]: Shareable responsive buyer summary page with aggregate payment workflow / 可分享的响应式姓名汇总页与总收款流程
// [POS]: Ring-sale buyer summary page / 售环姓名汇总页面
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources\RingSaleResource\Pages;

use App\Filament\Resources\RingSaleResource;
use App\Models\RingSale;
use App\Models\RingSalePayment;
use App\Models\User;
use App\Services\RingSaleBuyerSummaryService;
use App\Services\RingSaleService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;

class BuyerRingSaleSummary extends Page
{
    protected static string $resource = RingSaleResource::class;

    protected string $view = 'filament.resources.ring-sale-resource.pages.buyer-summary';

    public string $buyerName = '';

    public static function canAccess(array $parameters = []): bool
    {
        return RingSaleResource::hasModulePermission('view');
    }

    public function mount(?string $buyerName = null): void
    {
        abort_unless(RingSaleResource::hasModulePermission('view'), 403);

        $this->buyerName = trim((string) ($buyerName ?? request()->query('buyer_name', '')));
    }

    public function getTitle(): string
    {
        return $this->buyerName === '' ? '姓名售环汇总' : "{$this->buyerName} · 售环汇总";
    }

    public function getHeading(): string
    {
        return $this->getTitle();
    }

    public function getSubheading(): string
    {
        return $this->buyerName === ''
            ? '从售环列表点击姓名进入对应汇总页面。'
            : '按姓名精确匹配，汇总有效售环记录并保留完整历史明细。';
    }

    public function getBreadcrumb(): ?string
    {
        return '姓名汇总';
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'summary' => $this->getBuyerSummary(),
        ];
    }

    /** @return array<string, mixed> */
    public function getBuyerSummary(): array
    {
        return app(RingSaleBuyerSummaryService::class)->summarize($this->buyerName);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToRingSales')
                ->label('返回售环列表')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(RingSaleResource::getUrl('index')),
            $this->aggregatePaymentAction(),
        ];
    }

    public function aggregatePaymentAction(): Action
    {
        return Action::make('aggregatePayment')
            ->label(fn (): string => $this->getBuyerSummary()['unpaid_amount_cent'] > 0 ? '登记总收款' : '已付清')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (): bool => RingSaleResource::hasModulePermission('update'))
            ->disabled(fn (): bool => $this->getBuyerSummary()['unpaid_amount_cent'] <= 0)
            ->schema([
                DatePicker::make('payment_date')
                    ->label('收款日期')
                    ->default(today())
                    ->maxDate(today())
                    ->required(),
                TextInput::make('amount_cent')
                    ->label('收款金额')
                    ->prefix('¥')
                    ->numeric()
                    ->step(0.01)
                    ->minValue(0.01)
                    ->default(fn (): float => $this->getBuyerSummary()['unpaid_amount_cent'] / 100)
                    ->required(),
                Textarea::make('remark')
                    ->label('备注')
                    ->rows(2),
            ])
            ->modalHeading(fn (): string => '登记总收款 · 尚欠 '.RingSaleResource::formatYuan($this->getBuyerSummary()['unpaid_amount_cent']))
            ->modalDescription(fn (): string => '确认后将按售环日期从旧到新分配到此姓名下的有效售环单；部分收款会自动拆分为多笔售环收款流水。')
            ->modalSubmitActionLabel('确认登记总收款')
            ->requiresConfirmation()
            ->modalWidth(Width::Medium)
            ->action(function (array $data): void {
                $result = app(RingSaleService::class)->addPaymentForBuyer(
                    $this->buyerName,
                    [
                        ...$data,
                        'amount_cent' => RingSaleResource::yuanInputToCent($data['amount_cent'] ?? 0),
                    ],
                    $this->admin(),
                );

                Notification::make()
                    ->title('总收款已登记')
                    ->body('已分配到 '.count($result['affected_sale_ids']).' 笔售环记录。')
                    ->success()
                    ->send();
            });
    }

    /** @return array<int, Action> */
    public function getSaleActions(RingSale $sale): array
    {
        return array_values(array_filter([
            $this->actionForSale('viewSale', $sale),
            $this->actionForSale('editSale', $sale),
            $this->actionForSale('addPayment', $sale),
            $this->actionForSale('editPayment', $sale),
            $this->actionForSale('voidPayment', $sale),
            $this->actionForSale('voidSale', $sale),
        ]));
    }

    public function viewSaleAction(): Action
    {
        return Action::make('viewSale')
            ->label('查看详情')
            ->icon('heroicon-o-eye')
            ->visible(fn (array $arguments): bool => $this->canSaleAction($arguments, 'view'))
            ->modalHeading(fn (array $arguments): string => '售环单 '.$this->saleFromArguments($arguments)->sale_no)
            ->modalContent(fn (array $arguments): View => RingSaleResource::detailView($this->saleFromArguments($arguments)))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('关闭')
            ->modalWidth(Width::FiveExtraLarge)
            ->slideOver();
    }

    public function editSaleAction(): Action
    {
        return Action::make('editSale')
            ->label('编辑售环单')
            ->icon('heroicon-o-pencil-square')
            ->visible(fn (array $arguments): bool => $this->canSaleAction($arguments, 'update', 'active'))
            ->fillForm(fn (array $arguments): array => RingSaleResource::formData($this->saleFromArguments($arguments)))
            ->schema(fn (array $arguments): array => RingSaleResource::saleFormComponents(
                false,
                $this->saleFromArgumentsOrNull($arguments)?->paid_amount_cent ?? 0,
            ))
            ->modalHeading(fn (array $arguments): string => '编辑 '.$this->saleFromArguments($arguments)->sale_no)
            ->modalSubmitActionLabel('保存修改')
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth(Width::ScreenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'ring-sale-entry-modal'])
            ->action(function (array $data, array $arguments): void {
                app(RingSaleService::class)->update(
                    $this->saleFromArguments($arguments),
                    RingSaleResource::normalizeActionData($data, false),
                    $this->admin(),
                );

                Notification::make()->title('售环单已更新')->success()->send();
            });
    }

    public function addPaymentAction(): Action
    {
        return Action::make('addPayment')
            ->label('登记收款')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (array $arguments): bool => $this->canSaleAction($arguments, 'update', 'active')
                && $this->saleFromArguments($arguments)->unpaid_amount_cent > 0)
            ->schema([
                DatePicker::make('payment_date')->label('收款日期')->default(today())->maxDate(today())->required(),
                TextInput::make('amount_cent')->label('收款金额')->prefix('¥')->numeric()->step(0.01)->minValue(0.01)->required(),
                Textarea::make('remark')->label('备注')->rows(2),
            ])
            ->modalHeading(fn (array $arguments): string => '登记收款 · 尚欠 '.RingSaleResource::formatYuan($this->saleFromArguments($arguments)->unpaid_amount_cent))
            ->modalSubmitActionLabel('确认收款')
            ->action(function (array $data, array $arguments): void {
                app(RingSaleService::class)->addPayment(
                    $this->saleFromArguments($arguments),
                    [
                        ...$data,
                        'amount_cent' => RingSaleResource::yuanInputToCent($data['amount_cent'] ?? 0),
                    ],
                    $this->admin(),
                );

                Notification::make()->title('收款已登记')->success()->send();
            });
    }

    public function editPaymentAction(): Action
    {
        return Action::make('editPayment')
            ->label('修改收款')
            ->icon('heroicon-o-pencil')
            ->visible(fn (array $arguments): bool => $this->canSaleAction($arguments, 'update', 'active')
                && $this->saleFromArguments($arguments)->payments()->where('status', 'active')->exists())
            ->schema(fn (array $arguments): array => [
                Select::make('payment_id')
                    ->label('选择收款流水')
                    ->options(function () use ($arguments): array {
                        $sale = $this->saleFromArgumentsOrNull($arguments);
                        if (! $sale) {
                            return [];
                        }

                        return $sale->payments()
                            ->where('status', 'active')
                            ->orderBy('payment_date')
                            ->get()
                            ->mapWithKeys(fn (RingSalePayment $payment): array => [
                                $payment->id => $payment->payment_date->format('Y-m-d').' · '.RingSaleResource::formatYuan($payment->amount_cent),
                            ])
                            ->all();
                    })
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
            ->action(function (array $data, array $arguments): void {
                $payment = $this->saleFromArguments($arguments)
                    ->payments()
                    ->whereKey($data['payment_id'])
                    ->where('status', 'active')
                    ->firstOrFail();

                app(RingSaleService::class)->updatePayment(
                    $payment,
                    [
                        ...$data,
                        'amount_cent' => RingSaleResource::yuanInputToCent($data['amount_cent'] ?? 0),
                    ],
                    $this->admin(),
                );

                Notification::make()->title('收款流水已更新')->success()->send();
            });
    }

    public function voidPaymentAction(): Action
    {
        return Action::make('voidPayment')
            ->label('作废收款')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (array $arguments): bool => $this->canSaleAction($arguments, 'delete')
                && $this->saleFromArguments($arguments)->payments()->where('status', 'active')->exists())
            ->schema(fn (array $arguments): array => [
                Select::make('payment_id')
                    ->label('选择收款流水')
                    ->options(function () use ($arguments): array {
                        $sale = $this->saleFromArgumentsOrNull($arguments);
                        if (! $sale) {
                            return [];
                        }

                        return $sale->payments()
                            ->where('status', 'active')
                            ->orderBy('payment_date')
                            ->get()
                            ->mapWithKeys(fn (RingSalePayment $payment): array => [
                                $payment->id => $payment->payment_date->format('Y-m-d').' · '.RingSaleResource::formatYuan($payment->amount_cent),
                            ])
                            ->all();
                    })
                    ->required(),
                Textarea::make('void_reason')->label('作废原因')->required()->rows(2),
            ])
            ->requiresConfirmation()
            ->modalHeading('作废收款流水')
            ->modalSubmitActionLabel('确认作废')
            ->action(function (array $data, array $arguments): void {
                $payment = $this->saleFromArguments($arguments)
                    ->payments()
                    ->whereKey($data['payment_id'])
                    ->where('status', 'active')
                    ->firstOrFail();

                app(RingSaleService::class)->voidPayment($payment, $data['void_reason'], $this->admin());
                Notification::make()->title('收款流水已作废')->success()->send();
            });
    }

    public function voidSaleAction(): Action
    {
        return Action::make('voidSale')
            ->label('作废售环单')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->visible(fn (array $arguments): bool => $this->canSaleAction($arguments, 'delete', 'active'))
            ->schema([
                Textarea::make('void_reason')->label('作废原因')->required()->rows(3),
            ])
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => '作废 '.$this->saleFromArguments($arguments)->sale_no)
            ->modalDescription('作废后保留历史明细、收款和收据，并释放全部号码；此操作不能恢复。')
            ->modalSubmitActionLabel('确认作废')
            ->action(function (array $data, array $arguments): void {
                app(RingSaleService::class)->voidSale(
                    $this->saleFromArguments($arguments),
                    $data['void_reason'],
                    $this->admin(),
                );

                Notification::make()->title('售环单已作废')->success()->send();
            });
    }

    private function actionForSale(string $name, RingSale $sale): ?Action
    {
        $action = $this->getAction($name);
        if (! $action) {
            return null;
        }

        return (clone $action)->arguments(['sale_id' => $sale->id]);
    }

    private function canSaleAction(array $arguments, string $permission, ?string $status = null): bool
    {
        if (! RingSaleResource::hasModulePermission($permission)) {
            return false;
        }

        $saleId = (int) ($arguments['sale_id'] ?? 0);
        if ($saleId <= 0) {
            return false;
        }

        $sale = RingSale::query()
            ->where('buyer_name', $this->buyerName)
            ->find($saleId);

        return $sale instanceof RingSale
            && ($status === null || $sale->status === $status);
    }

    private function saleFromArguments(array $arguments): RingSale
    {
        $saleId = (int) ($arguments['sale_id'] ?? 0);

        return RingSale::query()
            ->where('buyer_name', $this->buyerName)
            ->whereKey($saleId)
            ->firstOrFail();
    }

    private function saleFromArgumentsOrNull(array $arguments): ?RingSale
    {
        $saleId = (int) ($arguments['sale_id'] ?? 0);
        if ($saleId <= 0) {
            return null;
        }

        return RingSale::query()
            ->where('buyer_name', $this->buyerName)
            ->whereKey($saleId)
            ->first();
    }

    private function admin(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
