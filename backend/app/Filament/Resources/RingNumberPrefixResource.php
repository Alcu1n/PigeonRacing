<?php

// [IN]: Ring-number prefixes, suffix widths, and enabled state / 足环号码前缀、尾号位数与启用状态
// [OUT]: Permission-aware prefix configuration screens / 受权限控制的前缀配置页面
// [POS]: Ring-number prefix Filament resource / 足环号码前缀 Filament 资源
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources;

use App\Filament\Clusters\RingSales;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Resources\RingNumberPrefixResource\Pages;
use App\Models\RingNumberPrefix;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RingNumberPrefixResource extends Resource
{
    use HasModulePermissions;

    protected static string $permissionModule = 'ring-sales';

    protected static ?string $cluster = RingSales::class;

    protected static ?string $model = RingNumberPrefix::class;

    protected static ?string $slug = 'prefixes';

    protected static ?string $navigationLabel = '号码前缀';

    protected static ?string $modelLabel = '号码前缀';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('prefix')
                ->label('前缀文本')
                ->placeholder('2026-13-055')
                ->required()
                ->maxLength(116)
                ->disabled(fn (?RingNumberPrefix $record): bool => $record?->isUsed() ?? false)
                ->helperText(fn (?RingNumberPrefix $record): ?string => $record?->isUsed() ? '该前缀已产生售环记录，前缀和尾号位数已锁定。' : '例如前缀 2026-13-055 配合尾号 0987，生成 2026-13-0550987。'),
            TextInput::make('suffix_width')
                ->label('尾号位数')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(12)
                ->default(4)
                ->required()
                ->disabled(fn (?RingNumberPrefix $record): bool => $record?->isUsed() ?? false),
            Toggle::make('is_enabled')
                ->label('启用')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                TextColumn::make('prefix')->label('前缀文本')->searchable()->copyable(),
                TextColumn::make('suffix_width')->label('尾号位数')->suffix(' 位'),
                TextColumn::make('items_count')->counts('items')->label('使用明细'),
                IconColumn::make('is_enabled')->label('启用')->boolean(),
                TextColumn::make('created_at')->label('创建时间')->dateTime(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRingNumberPrefixes::route('/'),
            'create' => Pages\CreateRingNumberPrefix::route('/create'),
            'edit' => Pages\EditRingNumberPrefix::route('/{record}/edit'),
        ];
    }
}
