<?php
// [IN]: Pigeon model records / 足环模型记录
// [OUT]: Filament pigeon management screens / Filament 足环管理页面
// [POS]: Backend admin pigeon resource / 后端后台足环资源
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources;

use App\Models\Pigeon;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PigeonResource extends Resource
{
    protected static ?string $model = Pigeon::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationLabel = '足环管理';
    protected static ?string $modelLabel = '足环';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('member_id')->label('会员')->relationship('member', 'loft_number')->required(),
            TextInput::make('loft_number')->label('会员棚号')->required(),
            TextInput::make('participant_name')->label('参赛名')->required(),
            TextInput::make('ring_number')->label('足环号码')->required(),
            TextInput::make('status')->label('状态')->default('normal')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('ring_number')->label('足环号码')->searchable(),
            TextColumn::make('loft_number')->label('棚号')->searchable(),
            TextColumn::make('participant_name')->label('参赛名')->searchable(),
            TextColumn::make('status')->label('状态'),
            TextColumn::make('created_at')->label('导入时间')->dateTime(),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
