<?php
// [IN]: RaceProject model records with cent-stored prices / 以分存储价格的赛事项目模型记录
// [OUT]: Filament project rule configuration screens using yuan labels / 使用元单位标签的 Filament 项目规则配置页面
// [POS]: Backend admin race project resource / 后端后台赛事项目资源
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources;

use App\Filament\Resources\RaceProjectResource\Pages;
use App\Models\RaceProject;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RaceProjectResource extends Resource
{
    protected static ?string $model = RaceProject::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = '报名项目';
    protected static ?string $modelLabel = '报名项目';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('race_id')->label('赛事')->relationship('race', 'name')->required(),
            TextInput::make('name')->label('项目名称')->required()->maxLength(128),
            TextInput::make('group_size')->label('项目羽数')->helperText('1 显示在单羽矩阵；大于 1 显示为多羽组合。')->numeric()->minValue(1)->required(),
            TextInput::make('price_cent')
                ->label('报名金额（元）')
                ->numeric()
                ->minValue(0)
                ->suffix('元')
                ->formatStateUsing(fn ($state): ?string => $state === null ? null : self::yuanFromCent((int) $state))
                ->dehydrateStateUsing(fn ($state): int => (int) round(((float) $state) * 100))
                ->required(),
            TextInput::make('sort_order')->label('排序')->numeric()->default(0),
            Toggle::make('is_enabled')->label('启用')->default(true),
            Toggle::make('allow_repeat_pigeon_in_project')->label('允许同足环在本项目重复使用')->default(false),
            TextInput::make('max_entries_per_member')->label('每会员本项目报名上限')->numeric(),
            TextInput::make('max_usage_per_pigeon')->label('每足环最大使用次数')->numeric(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('race.name')->label('赛事')->searchable(),
            TextColumn::make('name')->label('项目')->searchable(),
            TextColumn::make('group_size')->label('羽数'),
            TextColumn::make('price_cent')->label('金额（元）')->formatStateUsing(fn (?int $state): string => self::yuanFromCent($state ?? 0)),
            IconColumn::make('is_enabled')->label('启用')->boolean(),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRaceProjects::route('/'),
            'create' => Pages\CreateRaceProject::route('/create'),
            'edit' => Pages\EditRaceProject::route('/{record}/edit'),
        ];
    }

    private static function yuanFromCent(int $cent): string
    {
        $yuan = $cent / 100;

        return rtrim(rtrim(number_format($yuan, 2, '.', ''), '0'), '.');
    }
}
