<?php

// [IN]: Pigeon library records / 足环库记录
// [OUT]: Filament pigeon library management screens / Filament 足环库管理页面
// [POS]: Backend admin pigeon library resource / 后端后台足环库资源
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Resources\PigeonLibraryResource\Pages;
use App\Models\PigeonLibrary;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PigeonLibraryResource extends Resource
{
    use HasModulePermissions;

    protected static string $permissionModule = 'pigeon-libraries';

    protected static ?string $model = PigeonLibrary::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('足环库管理');
    }

    public static function getModelLabel(): string
    {
        return __('足环库');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('足环库名称'))->required()->maxLength(128)->unique(ignoreRecord: true),
            TextInput::make('sort_order')->label(__('排序'))->numeric()->default(0),
            Toggle::make('is_enabled')->label(__('启用'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('足环库名称'))->searchable(),
            TextColumn::make('pigeons_count')->label(__('足环数'))->counts('pigeons'),
            TextColumn::make('sort_order')->label(__('排序')),
            IconColumn::make('is_enabled')->label(__('启用'))->boolean(),
            TextColumn::make('updated_at')->label(__('更新时间'))->dateTime(),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPigeonLibraries::route('/'),
            'create' => Pages\CreatePigeonLibrary::route('/create'),
            'edit' => Pages\EditPigeonLibrary::route('/{record}/edit'),
        ];
    }
}
