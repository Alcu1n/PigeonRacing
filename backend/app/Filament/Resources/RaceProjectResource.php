<?php

// [IN]: RaceProject model records with cent-stored prices / 以分存储价格的赛事项目模型记录
// [OUT]: Filament project rule configuration screens using race currency labels / 使用赛事币种标签的 Filament 项目规则配置页面
// [POS]: Backend admin race project resource / 后端后台赛事项目资源
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources;

use App\Enums\CurrencyCode;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Resources\RaceProjectResource\Pages;
use App\Models\PigeonLibrary;
use App\Models\Race;
use App\Models\RaceProject;
use App\Models\RegistrationCategory;
use App\Support\CurrencyFormatter;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RaceProjectResource extends Resource
{
    use HasModulePermissions;

    protected static string $permissionModule = 'race-projects';

    protected static ?string $model = RaceProject::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('报名项目');
    }

    public static function getModelLabel(): string
    {
        return __('报名项目');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('race_id')->label(__('赛事'))->relationship('race', 'name')->required(),
            Select::make('pigeon_library_id')
                ->label(__('足环库'))
                ->options(fn (): array => PigeonLibrary::query()
                    ->orderByDesc('is_enabled')
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->default(fn (): int => PigeonLibrary::default()->id)
                ->searchable()
                ->required(fn (callable $get): bool => (bool) $get('is_enabled'))
                ->helperText(__('启用项目必须选择足环库；会员报名时只能选择该库内足环。')),
            Select::make('project_type')
                ->label(__('项目类型'))
                ->options([
                    RaceProject::TYPE_STANDARD => __('普通项目（单羽/多羽）'),
                    RaceProject::TYPE_PROGRESSIVE_STAGE => __('递进阶段项目'),
                ])
                ->default(RaceProject::TYPE_STANDARD)
                ->required(),
            Select::make('registration_category_id')
                ->label(__('所属递进类别'))
                ->options(fn (): array => RegistrationCategory::query()->with('race')->orderByDesc('id')->get()->mapWithKeys(
                    fn (RegistrationCategory $category): array => [$category->id => ($category->race?->name ? $category->race->name.' · ' : '').$category->name]
                )->all())
                ->searchable()
                ->helperText(__('仅项目类型为“递进阶段项目”时选择。')),
            TextInput::make('stage_order')
                ->label(__('阶段顺序'))
                ->numeric()
                ->minValue(1)
                ->helperText(__('递进阶段项目按此顺序判断上一阶段资格。第一阶段填 1。')),
            TextInput::make('name')->label(__('项目名称'))->required()->maxLength(128),
            TextInput::make('group_size')->label(__('项目羽数'))->helperText(__('普通项目：1 显示在单羽矩阵，大于 1 显示为多羽组合；递进阶段项目：1 为单羽递进，大于 1 为整组递进。'))->numeric()->minValue(1)->required(),
            TextInput::make('price_cent')
                ->label(__('报名金额'))
                ->numeric()
                ->minValue(0)
                ->suffix(fn (Get $get): string => CurrencyCode::fromValue(Race::query()->find($get('race_id'))?->currency_code)->symbol())
                ->formatStateUsing(fn ($state): ?string => $state === null ? null : self::yuanFromCent((int) $state))
                ->dehydrateStateUsing(fn ($state): int => (int) round(((float) $state) * 100))
                ->required(),
            TextInput::make('sort_order')->label(__('排序'))->numeric()->default(0),
            Toggle::make('is_enabled')->label(__('启用'))->default(true),
            Toggle::make('allow_repeat_pigeon_in_project')->label(__('允许同足环在本项目重复使用'))->default(false),
            TextInput::make('max_entries_per_member')->label(__('每会员本项目报名上限'))->numeric(),
            TextInput::make('max_usage_per_pigeon')->label(__('每足环最大使用次数'))->numeric(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('race.name')->label(__('赛事'))->searchable(),
            TextColumn::make('pigeonLibrary.name')->label(__('足环库'))->placeholder('-')->searchable(),
            TextColumn::make('project_type')
                ->label(__('类型'))
                ->formatStateUsing(fn (?string $state): string => $state === RaceProject::TYPE_PROGRESSIVE_STAGE ? __('递进阶段') : __('普通项目'))
                ->badge(),
            TextColumn::make('registrationCategory.name')->label(__('递进类别'))->placeholder('-'),
            TextColumn::make('name')->label(__('项目'))->searchable(),
            TextColumn::make('stage_order')->label(__('阶段'))->placeholder('-'),
            TextColumn::make('group_size')->label(__('羽数')),
            TextColumn::make('price_cent')->label(__('金额'))->formatStateUsing(fn (?int $state, RaceProject $record): string => CurrencyFormatter::format($state ?? 0, $record->race?->currency_code)),
            IconColumn::make('is_enabled')->label(__('启用'))->boolean(),
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
