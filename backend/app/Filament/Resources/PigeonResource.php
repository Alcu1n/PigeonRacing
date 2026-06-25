<?php
// [IN]: Member and pigeon model records / 会员与足环模型记录
// [OUT]: Filament pigeon management screens with member snapshot form / 带会员快照表单的 Filament 足环管理页面
// [POS]: Backend admin pigeon resource / 后端后台足环资源
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources;

use App\Filament\Resources\PigeonResource\Pages;
use App\Models\Member;
use App\Models\Pigeon;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PigeonResource extends Resource
{
    protected static ?string $model = Pigeon::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationLabel = '足环管理';
    protected static ?string $modelLabel = '足环';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('member_id')
                ->label('会员棚号')
                ->relationship('member', 'loft_number')
                ->searchable()
                ->preload()
                ->live()
                ->required()
                ->afterStateUpdated(function (Set $set, ?int $state): void {
                    $member = $state ? Member::query()->find($state) : null;

                    $set('loft_number', $member?->loft_number);
                    $set('participant_name', $member?->participant_name);
                }),
            TextInput::make('loft_number')->hidden()->dehydrated(),
            TextInput::make('participant_name')
                ->label('参赛名')
                ->readOnly()
                ->dehydrated(),
            TextInput::make('ring_number')
                ->label('足环号码')
                ->placeholder('2025-13-0001')
                ->required(fn (string $operation): bool => $operation === 'edit'),
            TextInput::make('batch_start_ring')
                ->label('批量起始足环号')
                ->placeholder('2025-13-0001')
                ->visible(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (string $operation): bool => $operation === 'create'),
            TextInput::make('batch_end_ring')
                ->label('批量结束足环号')
                ->placeholder('2025-13-0020')
                ->helperText('填写批量起止时，将忽略单个足环号码。')
                ->visible(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (string $operation): bool => $operation === 'create'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('ring_number')->label('足环号码')->searchable(),
            TextColumn::make('loft_number')->label('棚号')->searchable(),
            TextColumn::make('participant_name')->label('参赛名')->searchable(),
            TextColumn::make('status')->label('状态'),
            TextColumn::make('created_at')->label('导入时间')->dateTime(),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPigeons::route('/'),
            'create' => Pages\CreatePigeon::route('/create'),
            'edit' => Pages\EditPigeon::route('/{record}/edit'),
        ];
    }
}
