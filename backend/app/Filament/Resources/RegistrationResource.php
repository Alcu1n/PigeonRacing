<?php
// [IN]: Registration model records / 报名模型记录
// [OUT]: Filament registration review screens / Filament 报名查看确认页面
// [POS]: Backend admin registration resource / 后端后台报名资源
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources;

use App\Enums\RegistrationStatus;
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
            TextColumn::make('registration_no')->label('报名编号')->searchable(),
            TextColumn::make('race.name')->label('赛事'),
            TextColumn::make('member.loft_number')->label('棚号')->searchable(),
            TextColumn::make('member.participant_name')->label('参赛名')->searchable(),
            TextColumn::make('total_amount_cent')->label('金额（分）'),
            TextColumn::make('status')->label('状态'),
            TextColumn::make('submitted_at')->label('提交时间')->dateTime(),
        ])->recordActions([
            ViewAction::make(),
            Action::make('confirm')
                ->label('确认报名')
                ->requiresConfirmation()
                ->action(fn (Registration $record) => $record->forceFill([
                    'status' => RegistrationStatus::Confirmed,
                    'confirmed_at' => now(),
                    'confirmed_by' => auth()->id(),
                ])->save()),
        ]);
    }
}
