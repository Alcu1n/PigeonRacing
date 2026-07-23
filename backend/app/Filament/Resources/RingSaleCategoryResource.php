<?php

// [IN]: Ring-sale category names, fixed unit prices, and enabled state / 售环类别名称、固定单价与启用状态
// [OUT]: Permission-aware category configuration screens / 受权限控制的类别配置页面
// [POS]: Ring-sale category Filament resource / 售环类别 Filament 资源
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources;

use App\Filament\Clusters\RingSales;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Resources\RingSaleCategoryResource\Pages;
use App\Models\RingSaleCategory;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RingSaleCategoryResource extends Resource
{
    use HasModulePermissions;

    protected static string $permissionModule = 'ring-sales';

    protected static ?string $cluster = RingSales::class;

    protected static ?string $model = RingSaleCategory::class;

    protected static ?string $slug = 'categories';

    protected static ?string $navigationLabel = '足环类别';

    protected static ?string $modelLabel = '足环类别';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('类别名称')
                ->required()
                ->maxLength(100)
                ->unique(ignoreRecord: true)
                ->disabled(fn (?RingSaleCategory $record): bool => $record?->isUsed() ?? false)
                ->helperText(fn (?RingSaleCategory $record): ?string => $record?->isUsed() ? '该类别已产生售环记录，名称和单价已锁定。' : null),
            TextInput::make('unit_price_cent')
                ->label('每枚金额')
                ->prefix('¥')
                ->numeric()
                ->step(0.01)
                ->minValue(0.01)
                ->required()
                ->formatStateUsing(fn (?int $state): ?float => $state === null ? null : $state / 100)
                ->dehydrateStateUsing(fn (mixed $state): int => (int) round(((float) $state) * 100))
                ->disabled(fn (?RingSaleCategory $record): bool => $record?->isUsed() ?? false),
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
                TextColumn::make('name')->label('类别名称')->searchable(),
                TextColumn::make('unit_price_cent')
                    ->label('每枚金额')
                    ->formatStateUsing(fn (int $state): string => '¥'.number_format($state / 100, 2)),
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
            'index' => Pages\ListRingSaleCategories::route('/'),
            'create' => Pages\CreateRingSaleCategory::route('/create'),
            'edit' => Pages\EditRingSaleCategory::route('/{record}/edit'),
        ];
    }
}
