<?php
// [IN]: Registration model records and confirmation action / 报名模型记录与确认动作
// [OUT]: Filament registration review table ordered for confirmation workflow / 按确认流程排序的 Filament 报名查看表格
// [POS]: Backend admin registration resource / 后端后台报名资源
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources;

use App\Enums\RegistrationStatus;
use App\Filament\Resources\RegistrationResource\Pages;
use App\Models\Registration;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('status')
                ->label('确认报名')
                ->badge()
                ->formatStateUsing(fn (RegistrationStatus $state): string => $state === RegistrationStatus::Confirmed ? '已确认' : '确认报名')
                ->color(fn (RegistrationStatus $state): string => $state === RegistrationStatus::Confirmed ? 'success' : 'warning')
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
                ->state(fn (Registration $record): string => $record->status->value),
            TextColumn::make('submitted_at')->label('提交时间')->dateTime(),
        ])->recordActions([
            ViewAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegistrations::route('/'),
            'view' => Pages\ViewRegistration::route('/{record}'),
        ];
    }

    private static function confirmRegistration(Registration $record): void
    {
        if ($record->status === RegistrationStatus::Confirmed) {
            return;
        }

        $record->forceFill([
            'status' => RegistrationStatus::Confirmed,
            'confirmed_at' => now(),
            'confirmed_by' => auth()->id(),
        ])->save();
    }
}
