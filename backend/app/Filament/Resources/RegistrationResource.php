<?php
// [IN]: Registration model records, snapshot matrix service, and confirmation action / 报名模型记录、快照矩阵服务与确认动作
// [OUT]: Filament registration review table, bulk confirm, edit entry, localized status badges, and dense detail matrix / 带批量确认、编辑入口、本地化状态徽标与高密度详情矩阵的 Filament 报名审核表格
// [POS]: Backend admin registration resource / 后端后台报名资源
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources;

use App\Enums\RegistrationStatus;
use App\Filament\Resources\RegistrationResource\Pages;
use App\Models\Registration;
use App\Services\RaceCacheService;
use App\Services\RegistrationDetailMatrixService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class RegistrationResource extends Resource
{
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
                ->icon('heroicon-o-pencil-square')
                ->url(fn (Registration $record): string => self::getUrl('edit-data', ['record' => $record])),
        ])->bulkActions([
            BulkAction::make('confirmSelected')
                ->label('确认报名')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->action(fn (Collection $records) => self::confirmRegistrations($records))
                ->successNotificationTitle('已批量确认报名'),
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

    private static function formatYuan(?int $cent): string
    {
        return rtrim(rtrim(number_format(($cent ?? 0) / 100, 2, '.', ''), '0'), '.');
    }
}
