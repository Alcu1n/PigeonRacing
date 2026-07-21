<?php

// [IN]: Registration model records, snapshot matrix service, confirmation action, and deletion requests / 报名模型记录、快照矩阵服务、确认动作与删除请求
// [OUT]: Filament registration review table, bulk confirm/delete, edit entry, localized status badges, and dense detail matrix / 带批量确认/删除、编辑入口、本地化状态徽标与高密度详情矩阵的 Filament 报名审核表格
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
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RegistrationResource extends Resource
{
    use HasModulePermissions;

    protected static string $permissionModule = 'registrations';

    protected static ?string $model = Registration::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = '报名记录';

    protected static ?string $modelLabel = '报名记录';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('报名概览')
                ->columns(3)
                ->schema([
                    TextEntry::make('registration_no')->label('报名编号')->placeholder('-'),
                    TextEntry::make('race.name')->label('赛事名称')->placeholder('-'),
                    TextEntry::make('status')
                        ->label('确认状态')
                        ->badge()
                        ->formatStateUsing(fn (RegistrationStatus $state): string => Registration::statusLabel($state))
                        ->color(fn (RegistrationStatus $state): string => Registration::statusColor($state)),
                    TextEntry::make('member.loft_number')->label('会员棚号')->placeholder('-'),
                    TextEntry::make('member.participant_name')->label('会员参赛名')->placeholder('-'),
                    TextEntry::make('total_amount_cent')
                        ->label('总金额（元）')
                        ->formatStateUsing(fn (?int $state): string => self::formatYuan($state)),
                    TextEntry::make('submitted_at')->label('提交时间')->dateTime()->placeholder('-'),
                    TextEntry::make('confirmed_at')->label('确认时间')->dateTime()->placeholder('-'),
                    TextEntry::make('remark')->label('备注')->placeholder('-'),
                ]),
            View::make('filament.resources.registration-resource.registration-detail-matrix')
                ->viewData(fn (Registration $record): array => [
                    'matrix' => app(RegistrationDetailMatrixService::class)->matrix($record),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('status')
                ->label('确认报名')
                ->badge()
                ->formatStateUsing(fn (RegistrationStatus $state): string => Registration::statusLabel($state))
                ->color(fn (RegistrationStatus $state): string => Registration::statusColor($state))
                ->action(
                    Action::make('confirmFromColumn')
                        ->label('确认报名')
                        ->visible(fn (): bool => self::hasModulePermission('update'))
                        ->requiresConfirmation()
                        ->disabled(fn (Registration $record): bool => $record->status === RegistrationStatus::Confirmed)
                        ->action(fn (Registration $record) => self::confirmRegistration($record)),
                ),
            TextColumn::make('member.loft_number')->label('棚号')->searchable(),
            TextColumn::make('member.participant_name')->label('参赛名')->searchable(),
            TextColumn::make('total_amount_cent')
                ->label('金额（元）')
                ->formatStateUsing(fn (?int $state): string => rtrim(rtrim(number_format(($state ?? 0) / 100, 2, '.', ''), '0'), '.')),
            TextColumn::make('registration_no')->label('报名编号')->searchable(),
            TextColumn::make('race.name')->label('赛事'),
            TextColumn::make('status_text')
                ->label('状态')
                ->badge()
                ->state(fn (Registration $record): string => Registration::statusLabel($record->status))
                ->color(fn (Registration $record): string => Registration::statusColor($record->status)),
            TextColumn::make('submitted_at')->label('提交时间')->dateTime(),
        ])->recordActions([
            ViewAction::make(),
            Action::make('editRegistrationData')
                ->label('修改报名数据')
                ->visible(fn (): bool => self::hasModulePermission('update'))
                ->icon('heroicon-o-pencil-square')
                ->url(fn (Registration $record): string => self::getUrl('edit-data', ['record' => $record])),
            Action::make('deleteRegistration')
                ->label('删除报名记录')
                ->visible(fn (): bool => self::hasModulePermission('delete'))
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('删除报名记录')
                ->modalDescription('此操作会删除该报名记录、普通报名明细和递进报名明细。删除后会员端不再恢复这条报名。')
                ->modalSubmitActionLabel('确认删除')
                ->action(function (Registration $record): void {
                    abort_unless(self::hasModulePermission('delete'), 403);
                    $deleted = self::deleteRegistrations(collect([$record]));

                    Notification::make()
                        ->title("已删除 {$deleted} 条报名记录")
                        ->success()
                        ->send();
                }),
        ])->bulkActions([
            BulkAction::make('confirmSelected')
                ->label('确认报名')
                ->visible(fn (): bool => self::hasModulePermission('update'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->action(function (Collection $records): int {
                    abort_unless(self::hasModulePermission('update'), 403);

                    return self::confirmRegistrations($records);
                })
                ->successNotificationTitle('已批量确认报名'),
            BulkAction::make('deleteSelectedRegistrations')
                ->label('删除报名记录')
                ->visible(fn (): bool => self::hasModulePermission('delete'))
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('删除所选报名记录')
                ->modalDescription('此操作会删除所选报名记录、普通报名明细和递进报名明细。删除后会员端不再恢复这些报名。')
                ->modalSubmitActionLabel('确认删除')
                ->action(function (Collection $records): void {
                    abort_unless(self::hasModulePermission('delete'), 403);
                    $deleted = self::deleteRegistrations($records);

                    Notification::make()
                        ->title("已删除 {$deleted} 条报名记录")
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

    private static function formatYuan(?int $cent): string
    {
        return rtrim(rtrim(number_format(($cent ?? 0) / 100, 2, '.', ''), '0'), '.');
    }
}
