<?php

// [IN]: Administrator user records and permission catalog / 管理员用户记录与权限目录
// [OUT]: Super-admin-only administrator and permission management screens / 仅超级管理员可用的管理员与权限管理界面
// [POS]: Backend permission-management resource / 后台权限管理资源
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources;

use App\Filament\Resources\AdminUserResource\Pages;
use App\Models\User;
use App\Support\AdminPermissions;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AdminUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = '权限管理';

    protected static ?string $modelLabel = '管理员';

    public static function canViewAny(): bool
    {
        return auth()->user() instanceof User && auth()->user()->isSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny() && $record instanceof User && ! $record->isSuperAdmin() && $record->isNot(auth()->user());
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function canDeleteAny(): bool
    {
        return static::canViewAny();
    }

    public static function getEloquentQuery(): Builder
    {
        abort_unless(static::canViewAny(), 403);

        return parent::getEloquentQuery();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('管理员信息')->schema([
                TextInput::make('name')->label('姓名')->required()->maxLength(128),
                TextInput::make('phone')->label('手机号')->maxLength(32)->requiredWithout('email')->unique(ignoreRecord: true),
                TextInput::make('email')->label('邮箱')->email()->maxLength(255)->requiredWithout('phone')->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->label('密码')
                    ->password()
                    ->minLength(8)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn ($state): bool => filled($state)),
            ])->columns(2),
            Section::make('业务权限')
                ->description('导入归入新增；导出与详情归入查看；发布、确认与阶段管理归入编辑；批量或全部删除归入删除。')
                ->schema([
                    CheckboxList::make('permissions')
                        ->label('允许操作')
                        ->options(static::permissionOptions())
                        ->columns(4)
                        ->bulkToggleable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('姓名')->searchable(),
                TextColumn::make('phone')->label('手机号')->searchable()->placeholder('-'),
                TextColumn::make('email')->label('邮箱')->searchable()->placeholder('-'),
                TextColumn::make('roles.name')->label('角色')->badge(),
                TextColumn::make('updated_at')->label('更新时间')->dateTime(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->requiresConfirmation(),
            ]);
    }

    /** @return array<string, string> */
    public static function permissionOptions(): array
    {
        $options = [];
        foreach (AdminPermissions::grouped() as $group) {
            foreach ($group['permissions'] as $permission => $actionLabel) {
                $options[$permission] = "{$group['label']} · {$actionLabel}";
            }
        }

        return $options;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminUsers::route('/'),
            'create' => Pages\CreateAdminUser::route('/create'),
            'edit' => Pages\EditAdminUser::route('/{record}/edit'),
        ];
    }
}
