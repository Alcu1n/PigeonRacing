<?php
// [IN]: Race model records and lifecycle enum values / 赛事模型记录与生命周期枚举值
// [OUT]: Filament race configuration screens with Chinese status select and detail publication actions / 带中文状态下拉与明细发布动作的 Filament 赛事配置页面
// [POS]: Backend admin race resource / 后端后台赛事资源
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources;

use App\Filament\Resources\RaceResource\Pages;
use App\Enums\RaceStatus;
use App\Models\Race;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
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
            TextColumn::make('registration_details_published_at')->label('明细发布')->dateTime()->placeholder('未发布'),
        ])->recordActions([
            Action::make('publishDetails')
                ->label('明细发布')
                ->icon('heroicon-o-megaphone')
                ->color('success')
                ->visible(fn (Race $record): bool => self::canPublishDetails($record) && ! $record->hasPublishedRegistrationDetails())
                ->form([self::detailsScopeSelect()])
                ->modalHeading('发布报名明细')
                ->modalSubmitActionLabel('确认发布')
                ->action(function (Race $record, array $data): void {
                    self::publishDetails($record, (string) ($data['registration_details_scope'] ?? Race::DETAILS_SCOPE_CONFIRMED_ONLY));
                    Notification::make()->title('报名明细已发布')->success()->send();
                }),
            Action::make('updateDetailsPublication')
                ->label('更新发布设置')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->visible(fn (Race $record): bool => $record->hasPublishedRegistrationDetails())
                ->fillForm(fn (Race $record): array => [
                    'registration_details_scope' => $record->registration_details_scope ?: Race::DETAILS_SCOPE_CONFIRMED_ONLY,
                ])
                ->form([self::detailsScopeSelect()])
                ->modalHeading('更新报名明细发布设置')
                ->modalSubmitActionLabel('保存设置')
                ->action(function (Race $record, array $data): void {
                    self::publishDetails($record, (string) ($data['registration_details_scope'] ?? Race::DETAILS_SCOPE_CONFIRMED_ONLY), false);
                    Notification::make()->title('发布设置已更新')->success()->send();
                }),
            Action::make('viewPublishedDetails')
                ->label('查看明细')
                ->icon('heroicon-o-eye')
                ->url(fn (Race $record): string => url("/races/{$record->id}/details"))
                ->openUrlInNewTab()
                ->visible(fn (Race $record): bool => $record->hasPublishedRegistrationDetails()),
            Action::make('unpublishDetails')
                ->label('取消发布')
                ->icon('heroicon-o-eye-slash')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (Race $record): bool => $record->hasPublishedRegistrationDetails())
                ->action(function (Race $record): void {
                    $record->forceFill(['registration_details_published_at' => null])->save();
                    Notification::make()->title('报名明细已取消发布')->success()->send();
                }),
            EditAction::make(),
            DeleteAction::make(),
        ]);
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

    private static function detailsScopeSelect(): Select
    {
        return Select::make('registration_details_scope')
            ->label('发布范围')
            ->options([
                Race::DETAILS_SCOPE_CONFIRMED_ONLY => '仅已确认',
                Race::DETAILS_SCOPE_ALL_SUBMITTED => '全部提交',
            ])
            ->default(Race::DETAILS_SCOPE_CONFIRMED_ONLY)
            ->required()
            ->helperText('“仅已确认”适合对会员公开最终报名结果；“全部提交”会同时显示未确认状态。');
    }

    private static function canPublishDetails(Race $record): bool
    {
        return $record->registration_end_at !== null && $record->registration_end_at->isPast();
    }

    private static function publishDetails(Race $record, string $scope, bool $touchPublishedAt = true): void
    {
        if (! in_array($scope, [Race::DETAILS_SCOPE_CONFIRMED_ONLY, Race::DETAILS_SCOPE_ALL_SUBMITTED], true)) {
            $scope = Race::DETAILS_SCOPE_CONFIRMED_ONLY;
        }

        $record->forceFill([
            'registration_details_scope' => $scope,
            'registration_details_published_at' => $touchPublishedAt || ! $record->registration_details_published_at
                ? now()
                : $record->registration_details_published_at,
        ])->save();
    }
}
