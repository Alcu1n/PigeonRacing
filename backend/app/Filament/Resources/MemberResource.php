<?php
// [IN]: Member model records / 会员模型记录
// [OUT]: Filament member CRUD screens / Filament 会员 CRUD 页面
// [POS]: Backend admin member resource / 后端后台会员资源
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources;

use App\Models\Member;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = '会员管理';
    protected static ?string $modelLabel = '会员';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('phone')->label('手机号')->required()->maxLength(32),
            TextInput::make('password')->label('密码')->password()->required(fn (string $operation): bool => $operation === 'create')->dehydrated(fn ($state): bool => filled($state)),
            TextInput::make('loft_number')->label('会员棚号')->required()->maxLength(64),
            TextInput::make('participant_name')->label('参赛名')->required()->maxLength(128),
            TextInput::make('status')->label('状态')->default('enabled')->required(),
            TextInput::make('remark')->label('备注'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('phone')->label('手机号')->searchable(),
            TextColumn::make('loft_number')->label('棚号')->searchable(),
            TextColumn::make('participant_name')->label('参赛名')->searchable(),
            TextColumn::make('pigeons_count')->counts('pigeons')->label('足环数量'),
            TextColumn::make('status')->label('状态'),
            TextColumn::make('last_login_at')->label('最近登录')->dateTime(),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
