<?php

// [IN]: Registration model records, snapshot matrix service, confirmation action, and deletion requests / 报名模型记录、快照矩阵服务、确认动作与删除请求
// [OUT]: Filament registration review table, latest-submission order, confirmation filter, identity-aware confirmation/deletion prompts, bulk confirm/delete, edit entry, localized status badges, prioritized overview, dense detail matrix, and receipt download column / 带最近提交排序、确认状态筛选、含身份信息的确认/删除提示、批量确认/删除、编辑入口、本地化状态徽标、重点概览、高密度详情矩阵与明细下载列的 Filament 报名审核表格
// [POS]: Backend admin registration resource / 后端后台报名资源
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources;

use App\Enums\RegistrationStatus;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Resources\RegistrationResource\Pages;
use App\Models\Registration;
use App\Services\RaceCacheService;
use App\Services\RegistrationDetailMatrixService;
use App\Support\CurrencyFormatter;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RegistrationResource extends Resource
{
    use HasModulePermissions;

    protected static string $permissionModule = 'registrations';

    protected static ?string $model = Registration::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('报名记录');
    }

    public static function getModelLabel(): string
    {
        return __('报名记录');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.resources.registration-resource.registration-overview')
                ->viewData(fn (Registration $record): array => ['registration' => $record]),
            View::make('filament.resources.registration-resource.registration-detail-matrix')
                ->viewData(fn (Registration $record): array => [
                    'registration' => $record,
                    'matrix' => app(RegistrationDetailMatrixService::class)->matrix($record),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $table
            ->defaultSort('submitted_at', 'desc')
            ->defaultPaginationPageOption(50);

        return $table->columns([
            TextColumn::make('status')
                ->label(__('确认报名'))
                ->badge()
                ->formatStateUsing(fn (RegistrationStatus $state): string => $state === RegistrationStatus::Confirmed ? __('已确认') : __('点击确认'))
                ->color(fn (RegistrationStatus $state): string => Registration::statusColor($state))
                ->action(
                    Action::make('confirmFromColumn')
                        ->label(__('确认报名'))
                        ->visible(fn (): bool => self::hasModulePermission('update'))
                        ->requiresConfirmation()
                        ->modalHeading(__('确认报名'))
                        ->modalDescription(fn (Registration $record): string => self::confirmationPrompt($record))
                        ->modalSubmitActionLabel(__('确认报名'))
                        ->disabled(fn (Registration $record): bool => $record->status === RegistrationStatus::Confirmed)
                        ->action(fn (Registration $record) => self::confirmRegistration($record)),
                ),
            TextColumn::make('member.loft_number')->label(__('棚号'))->searchable(),
            TextColumn::make('member.participant_name')->label(__('参赛名'))->searchable(),
            TextColumn::make('total_amount_cent')
                ->label(__('金额'))
                ->formatStateUsing(fn (?int $state, Registration $record): string => CurrencyFormatter::format($state ?? 0, $record->currency_code)),
            TextColumn::make('receipt_download')
                ->label(__('下载'))
                ->badge()
                ->color('gray')
                ->state(fn (): string => __('下载明细'))
                ->visible(fn (): bool => self::hasModulePermission('view'))
                ->url(fn (Registration $record): string => route('admin.registrations.receipt', ['registration' => $record]), shouldOpenInNewTab: true),
            TextColumn::make('registration_no')->label(__('报名编号'))->searchable(),
            TextColumn::make('race.name')->label(__('赛事')),
            TextColumn::make('status_text')
                ->label(__('状态'))
                ->badge()
                ->state(fn (Registration $record): string => Registration::statusLabel($record->status))
                ->color(fn (Registration $record): string => Registration::statusColor($record->status)),
            TextColumn::make('submitted_at')->label(__('提交时间'))->dateTime(),
        ])->filters([
            TernaryFilter::make('confirmation_status')
                ->label(__('确认状态'))
                ->placeholder(__('全部'))
                ->trueLabel(__('已确认'))
                ->falseLabel(__('未确认'))
                ->queries(
                    true: fn (Builder $query): Builder => $query->where('status', RegistrationStatus::Confirmed->value),
                    false: fn (Builder $query): Builder => $query->where('status', '!=', RegistrationStatus::Confirmed->value),
                ),
        ])->recordActions([
            ViewAction::make(),
            Action::make('editRegistrationData')
                ->label(__('修改报名数据'))
                ->visible(fn (): bool => self::hasModulePermission('update'))
                ->icon('heroicon-o-pencil-square')
                ->url(fn (Registration $record): string => self::getUrl('edit-data', ['record' => $record])),
            Action::make('deleteRegistration')
                ->label(__('删除报名记录'))
                ->visible(fn (): bool => self::hasModulePermission('delete'))
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('删除报名记录'))
                ->modalDescription(fn (Registration $record): string => self::deletionPrompt($record))
                ->modalSubmitActionLabel(__('确认删除'))
                ->action(function (Registration $record): void {
                    abort_unless(self::hasModulePermission('delete'), 403);
                    $deleted = self::deleteRegistrations(collect([$record]));

                    Notification::make()
                        ->title(__('已删除 :count 条报名记录', ['count' => $deleted]))
                        ->success()
                        ->send();
                }),
        ])->bulkActions([
            BulkAction::make('confirmSelected')
                ->label(__('确认报名'))
                ->visible(fn (): bool => self::hasModulePermission('update'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->action(function (Collection $records): int {
                    abort_unless(self::hasModulePermission('update'), 403);

                    return self::confirmRegistrations($records);
                })
                ->successNotificationTitle(__('已批量确认报名')),
            BulkAction::make('deleteSelectedRegistrations')
                ->label(__('删除报名记录'))
                ->visible(fn (): bool => self::hasModulePermission('delete'))
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('删除所选报名记录'))
                ->modalDescription(__('此操作会删除所选报名记录、普通报名明细和递进报名明细。删除后会员端不再恢复这些报名。'))
                ->modalSubmitActionLabel(__('确认删除'))
                ->action(function (Collection $records): void {
                    abort_unless(self::hasModulePermission('delete'), 403);
                    $deleted = self::deleteRegistrations($records);

                    Notification::make()
                        ->title(__('已删除 :count 条报名记录', ['count' => $deleted]))
                        ->success()
                        ->send();
                }),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegistrations::route('/'),
            'view' => Pages\ViewRegistration::route('/{record}'),
            'edit-data' => Pages\EditRegistrationData::route('/{record}/edit-data'),
        ];
    }

    private static function confirmRegistration(Registration $record): void
    {
        abort_unless(self::hasModulePermission('update'), 403);

        self::confirmRegistrations(collect([$record]));
    }

    private static function confirmationPrompt(Registration $record): string
    {
        return sprintf(
            __('请确认报名信息：棚号：%s；会员名：%s；总金额：%s。确认后将标记为已确认。'),
            self::loftNumber($record),
            self::memberName($record),
            self::formatAmount($record->total_amount_cent, $record),
        );
    }

    private static function deletionPrompt(Registration $record): string
    {
        return sprintf(
            __('即将删除报名记录：棚号：%s；会员名：%s。此操作会删除该报名记录、普通报名明细和递进报名明细，删除后会员端不再恢复这条报名。'),
            self::loftNumber($record),
            self::memberName($record),
        );
    }

    private static function loftNumber(Registration $record): string
    {
        $record->loadMissing('member');

        return $record->member?->loft_number ?? $record->loft_number_snapshot ?? '—';
    }

    private static function memberName(Registration $record): string
    {
        $record->loadMissing('member');

        return $record->member?->participant_name ?? $record->participant_name_snapshot ?? '—';
    }

    private static function formatAmount(?int $amountCent, Registration $record): string
    {
        return CurrencyFormatter::format($amountCent ?? 0, $record->currency_code);
    }

    public static function confirmRegistrations(iterable $records): int
    {
        $confirmed = 0;

        foreach ($records as $record) {
            if (! $record instanceof Registration || $record->status === RegistrationStatus::Confirmed) {
                continue;
            }

            $record->forceFill([
                'status' => RegistrationStatus::Confirmed,
                'confirmed_at' => now(),
                'confirmed_by' => auth()->id(),
            ])->save();

            $record->progressiveStageEntries()->update([
                'status' => RegistrationStatus::Confirmed->value,
                'confirmed_at' => now(),
                'confirmed_by' => auth()->id(),
            ]);

            $record->loadMissing(['race', 'member']);
            app(RaceCacheService::class)->forgetBootstrap($record->race, $record->member);
            $confirmed++;
        }

        return $confirmed;
    }

    public static function deleteRegistrations(iterable $records): int
    {
        $registrations = collect($records)
            ->filter(fn ($record): bool => $record instanceof Registration)
            ->values();

        return DB::transaction(function () use ($registrations): int {
            $deleted = 0;

            foreach ($registrations as $registration) {
                $registration->loadMissing(['race', 'member']);

                if (! $registration->exists) {
                    continue;
                }

                $registration->progressiveStageEntries()->delete();

                if (! $registration->delete()) {
                    continue;
                }

                if ($registration->race !== null && $registration->member !== null) {
                    app(RaceCacheService::class)->forgetBootstrap($registration->race, $registration->member);
                }

                $deleted++;
            }

            return $deleted;
        });
    }
}
