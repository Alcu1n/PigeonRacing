<?php
// [IN]: Race model records and lifecycle enum values / 赛事模型记录与生命周期枚举值
// [OUT]: Filament race configuration screens with Chinese status select / 带中文状态下拉的 Filament 赛事配置页面
// [POS]: Backend admin race resource / 后端后台赛事资源
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources;

use App\Filament\Resources\RaceResource\Pages;
use App\Enums\RaceStatus;
use App\Models\Race;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RaceResource extends Resource
{
    protected static ?string $model = Race::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-flag';
    protected static ?string $navigationLabel = '赛事管理';
    protected static ?string $modelLabel = '赛事';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('赛事名称')->required()->maxLength(128),
            TextInput::make('description')->label('赛事说明'),
            DateTimePicker::make('registration_start_at')->label('报名开始')->required(),
            DateTimePicker::make('registration_end_at')->label('报名截止')->required(),
            Select::make('status')
                ->label('状态')
                ->options([
                    RaceStatus::Draft->value => '草稿',
                    RaceStatus::Published->value => '发布',
                ])
                ->default(RaceStatus::Draft->value)
                ->required(),
            TextInput::make('config_version')->label('配置版本')->numeric()->default(1)->required(),
            Toggle::make('allow_member_edit')->label('允许会员截止前修改')->default(true),
            Toggle::make('require_admin_confirm')->label('需要后台确认')->default(true),
            Toggle::make('is_visible')->label('会员端可见')->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('赛事名称')->searchable(),
            TextColumn::make('registration_start_at')->label('开始')->dateTime(),
            TextColumn::make('registration_end_at')->label('截止')->dateTime(),
            TextColumn::make('status')
                ->label('状态')
                ->formatStateUsing(fn (RaceStatus|string $state): string => self::statusLabel($state)),
            TextColumn::make('config_version')->label('版本'),
            TextColumn::make('registrations_count')->counts('registrations')->label('报名人数'),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRaces::route('/'),
            'create' => Pages\CreateRace::route('/create'),
            'edit' => Pages\EditRace::route('/{record}/edit'),
        ];
    }

    private static function statusLabel(RaceStatus|string $state): string
    {
        $status = $state instanceof RaceStatus ? $state : RaceStatus::tryFrom($state);

        return match ($status) {
            RaceStatus::Draft => '草稿',
            RaceStatus::Published => '发布',
            RaceStatus::Closed => '已关闭',
            RaceStatus::Archived => '已归档',
            default => (string) $state,
        };
    }
}
