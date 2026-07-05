<?php
// [IN]: InformationPost model rows and rich HTML content / 信息发布模型行与富文本 HTML 内容
// [OUT]: Filament information publishing CRUD with TipTap RichEditor / 带 TipTap RichEditor 的 Filament 信息发布 CRUD
// [POS]: Backend admin information publishing resource / 后端后台信息发布资源
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources;

use App\Filament\Resources\InformationPostResource\Pages;
use App\Models\InformationPost;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InformationPostResource extends Resource
{
    protected static ?string $model = InformationPost::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-newspaper';
    protected static ?string $navigationLabel = '信息发布';
    protected static ?string $modelLabel = '信息发布';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('标题')->required()->maxLength(160),
            TextInput::make('slug')->label('链接标识')->helperText('可留空，系统会根据标题自动生成。')->maxLength(180)->unique(ignoreRecord: true),
            Select::make('category')
                ->label('分类')
                ->options(InformationPost::categoryOptions())
                ->default(InformationPost::CATEGORY_NOTICE)
                ->required(),
            Select::make('status')
                ->label('状态')
                ->options(InformationPost::statusOptions())
                ->default(InformationPost::STATUS_DRAFT)
                ->required(),
            Toggle::make('is_pinned')->label('置顶')->default(false),
            DateTimePicker::make('published_at')->label('发布时间')->helperText('发布状态下可留空，系统保存时自动填入当前时间。'),
            Textarea::make('summary')->label('摘要')->rows(3)->maxLength(240),
            RichEditor::make('content_html')
                ->label('正文')
                ->required()
                ->fileAttachmentsDisk('public')
                ->fileAttachmentsDirectory('information')
                ->fileAttachmentsVisibility('public')
                ->fileAttachmentsAcceptedFileTypes(['image/png', 'image/jpeg', 'image/gif', 'image/webp'])
                ->fileAttachmentsMaxSize(10240)
                ->customTextColors()
                ->toolbarButtons([
                    ['bold', 'italic', 'underline', 'strike', 'link', 'textColor'],
                    ['h2', 'h3', 'paragraph'],
                    ['alignStart', 'alignCenter', 'alignEnd'],
                    ['blockquote', 'bulletList', 'orderedList'],
                    ['table', 'attachFiles', 'horizontalRule'],
                    ['undo', 'redo'],
                ])
                ->floatingToolbars([
                    'table' => [
                        'tableAddColumnBefore',
                        'tableAddColumnAfter',
                        'tableDeleteColumn',
                        'tableAddRowBefore',
                        'tableAddRowAfter',
                        'tableDeleteRow',
                        'tableMergeCells',
                        'tableSplitCell',
                        'tableToggleHeaderRow',
                        'tableToggleHeaderCell',
                        'tableDelete',
                    ],
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->orderByDesc('is_pinned')->orderByDesc('published_at')->orderByDesc('id'))
            ->columns([
                IconColumn::make('is_pinned')->label('置顶')->boolean(),
                TextColumn::make('title')->label('标题')->searchable()->wrap(),
                TextColumn::make('category')
                    ->label('分类')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => InformationPost::categoryLabel($state)),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => InformationPost::statusLabel($state))
                    ->color(fn (?string $state): string => $state === InformationPost::STATUS_PUBLISHED ? 'success' : 'gray'),
                TextColumn::make('published_at')->label('发布时间')->dateTime()->placeholder('-'),
                TextColumn::make('updated_at')->label('更新时间')->dateTime(),
            ])
            ->filters([
                SelectFilter::make('category')->label('分类')->options(InformationPost::categoryOptions()),
                SelectFilter::make('status')->label('状态')->options(InformationPost::statusOptions()),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInformationPosts::route('/'),
            'create' => Pages\CreateInformationPost::route('/create'),
            'edit' => Pages\EditInformationPost::route('/{record}/edit'),
        ];
    }
}
