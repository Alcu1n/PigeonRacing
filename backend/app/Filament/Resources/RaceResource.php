<?php

// [IN]: Race model records and lifecycle enum values / 赛事模型记录与生命周期枚举值
// [OUT]: Filament race configuration screens with Chinese status select and detail publication actions / 带中文状态下拉与明细发布动作的 Filament 赛事配置页面
// [POS]: Backend admin race resource / 后端后台赛事资源
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources;

use App\Enums\CurrencyCode;
use App\Enums\RaceStatus;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Resources\RaceResource\Pages;
use App\Models\Race;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RaceResource extends Resource
{
    use HasModulePermissions;

    protected static string $permissionModule = 'races';

    protected static ?string $model = Race::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('赛事管理');
    }

    public static function getModelLabel(): string
    {
        return __('赛事');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('赛事名称'))->required()->maxLength(128),
            TextInput::make('description')->label(__('赛事说明')),
            DateTimePicker::make('registration_start_at')->label(__('报名开始'))->required(),
            DateTimePicker::make('registration_end_at')->label(__('报名截止'))->required(),
            Select::make('status')
                ->label(__('状态'))
                ->options([
                    RaceStatus::Draft->value => __('草稿'),
                    RaceStatus::Published->value => __('发布'),
                ])
                ->default(RaceStatus::Draft->value)
                ->required(),
            TextInput::make('config_version')->label(__('配置版本'))->numeric()->default(1)->required(),
            Toggle::make('allow_member_edit')->label(__('允许会员截止前修改'))->default(true),
            Toggle::make('require_admin_confirm')->label(__('需要后台确认'))->default(true),
            Toggle::make('is_visible')->label(__('会员端可见'))->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('赛事名称'))->searchable(),
            TextColumn::make('registration_start_at')->label(__('开始'))->dateTime(),
            TextColumn::make('registration_end_at')->label(__('截止'))->dateTime(),
            TextColumn::make('status')
                ->label(__('状态'))
                ->formatStateUsing(fn (RaceStatus|string $state): string => self::statusLabel($state)),
            TextColumn::make('config_version')->label(__('版本')),
            TextColumn::make('currency_code')
                ->label(__('币种'))
                ->formatStateUsing(fn (CurrencyCode|string|null $state): string => CurrencyCode::fromValue($state)->value),
            TextColumn::make('registrations_count')->counts('registrations')->label(__('报名人数')),
            TextColumn::make('registration_details_published_at')->label(__('明细发布'))->dateTime()->placeholder(__('未发布')),
        ])->recordActions([
            Action::make('publishDetails')
                ->label(__('明细发布'))
                ->icon('heroicon-o-megaphone')
                ->color('success')
                ->visible(fn (Race $record): bool => self::hasModulePermission('update') && self::canPublishDetails($record) && ! $record->hasPublishedRegistrationDetails())
                ->form([self::detailsScopeSelect()])
                ->modalHeading(__('发布报名明细'))
                ->modalSubmitActionLabel(__('确认发布'))
                ->action(function (Race $record, array $data): void {
                    abort_unless(self::hasModulePermission('update'), 403);
                    self::publishDetails($record, (string) ($data['registration_details_scope'] ?? Race::DETAILS_SCOPE_CONFIRMED_ONLY));
                    Notification::make()->title(__('报名明细已发布'))->success()->send();
                }),
            Action::make('updateDetailsPublication')
                ->label(__('更新发布设置'))
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->visible(fn (Race $record): bool => self::hasModulePermission('update') && $record->hasPublishedRegistrationDetails())
                ->fillForm(fn (Race $record): array => [
                    'registration_details_scope' => $record->registration_details_scope ?: Race::DETAILS_SCOPE_CONFIRMED_ONLY,
                ])
                ->form([self::detailsScopeSelect()])
                ->modalHeading(__('更新报名明细发布设置'))
                ->modalSubmitActionLabel(__('保存设置'))
                ->action(function (Race $record, array $data): void {
                    abort_unless(self::hasModulePermission('update'), 403);
                    self::publishDetails($record, (string) ($data['registration_details_scope'] ?? Race::DETAILS_SCOPE_CONFIRMED_ONLY), false);
                    Notification::make()->title(__('发布设置已更新'))->success()->send();
                }),
            Action::make('viewPublishedDetails')
                ->label(__('查看明细'))
                ->icon('heroicon-o-eye')
                ->url(fn (Race $record): string => url("/races/{$record->id}/details"))
                ->openUrlInNewTab()
                ->visible(fn (Race $record): bool => self::hasModulePermission('view') && $record->hasPublishedRegistrationDetails()),
            Action::make('unpublishDetails')
                ->label(__('取消发布'))
                ->icon('heroicon-o-eye-slash')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (Race $record): bool => self::hasModulePermission('update') && $record->hasPublishedRegistrationDetails())
                ->action(function (Race $record): void {
                    abort_unless(self::hasModulePermission('update'), 403);
                    $record->forceFill(['registration_details_published_at' => null])->save();
                    Notification::make()->title(__('报名明细已取消发布'))->success()->send();
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
            RaceStatus::Draft => __('草稿'),
            RaceStatus::Published => __('发布'),
            RaceStatus::Closed => __('已关闭'),
            RaceStatus::Archived => __('已归档'),
            default => (string) $state,
        };
    }

    private static function detailsScopeSelect(): Select
    {
        return Select::make('registration_details_scope')
            ->label(__('发布范围'))
            ->options([
                Race::DETAILS_SCOPE_CONFIRMED_ONLY => __('仅已确认'),
                Race::DETAILS_SCOPE_ALL_SUBMITTED => __('全部提交'),
            ])
            ->default(Race::DETAILS_SCOPE_CONFIRMED_ONLY)
            ->required()
            ->helperText(__('“仅已确认”适合对会员公开最终报名结果；“全部提交”会同时显示未确认状态。'));
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
