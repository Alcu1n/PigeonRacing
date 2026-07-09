<?php
// [IN]: Member model records with optional login credentials / 可选登录凭据的会员模型记录
// [OUT]: Filament member CRUD screens, selected deletion, and cache cleanup / 支持导入、可登录会员、所选删除与缓存清理的 Filament 会员 CRUD 页面
// [POS]: Backend admin member resource / 后端后台会员资源
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources;

use App\Filament\Resources\MemberResource\Pages;
use App\Models\Member;
use App\Services\RaceCacheService;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = '会员管理';
    protected static ?string $modelLabel = '会员';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('phone')->label('手机号')->maxLength(32)->unique(ignoreRecord: true),
            TextInput::make('password')->label('密码')->password()->dehydrated(fn ($state): bool => filled($state)),
            TextInput::make('loft_number')->label('会员棚号')->required()->maxLength(64)->unique(ignoreRecord: true),
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
        ])->recordActions([
            EditAction::make(),
            DeleteAction::make()
                ->using(fn (Member $record): bool => self::deleteMembers([$record]) === 1),
        ])
            ->bulkActions([
                BulkAction::make('deleteSelectedMembers')
                    ->label('删除所选会员')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('删除所选会员')
                    ->modalDescription('此操作会删除所选会员，并同步删除这些会员的足环和报名记录。')
                    ->modalSubmitActionLabel('确认删除')
                    ->action(function (Collection $records): void {
                        $deleted = self::deleteMembers($records);

                        Notification::make()
                            ->title("已删除 {$deleted} 个会员")
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function deleteMembers(iterable $records): int
    {
        $members = collect($records)
            ->filter(fn ($record): bool => $record instanceof Member)
            ->values();

        $memberIds = $members
            ->pluck('id')
            ->filter()
            ->unique()
            ->values();

        $deleted = DB::transaction(function () use ($members): int {
            $deleted = 0;

            foreach ($members as $member) {
                if ($member->exists && $member->delete()) {
                    $deleted++;
                }
            }

            return $deleted;
        });

        $memberIds->each(fn (int $memberId) => app(RaceCacheService::class)->forgetMemberPigeonsById($memberId));

        return $deleted;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMembers::route('/'),
            'create' => Pages\CreateMember::route('/create'),
            'edit' => Pages\EditMember::route('/{record}/edit'),
            'import' => Pages\ImportMembers::route('/import'),
        ];
    }
}
