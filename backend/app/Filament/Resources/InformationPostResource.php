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
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\RichEditor\TextColor;
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
use Filament\Support\Icons\Heroicon;

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
                ->textColors(self::editorTextColors())
                ->tools([
                    self::textColorTool('textColorRed', '红', 'red', '#dc2626'),
                    self::textColorTool('textColorAmber', '橙', 'amber', '#d97706'),
                    self::textColorTool('textColorGreen', '绿', 'green', '#16a34a'),
                    self::textColorTool('textColorBlue', '蓝', 'blue', '#2563eb'),
                    self::textColorTool('textColorPurple', '紫', 'purple', '#9333ea'),
                    self::textColorClearTool(),
                ])
                ->toolbarButtons([
                    [
                        'bold',
                        'italic',
                        'underline',
                        'strike',
                        'link',
                        'textColorRed',
                        'textColorAmber',
                        'textColorGreen',
                        'textColorBlue',
                        'textColorPurple',
                        'textColorClear',
                    ],
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

    private static function textColorTool(string $name, string $label, string $colorKey, string $color): RichEditorTool
    {
        return RichEditorTool::make($name)
            ->label($label)
            ->icon(Heroicon::Swatch)
            ->activeKey('textColor')
            ->activeOptions(['data-color' => $colorKey])
            ->jsHandler("\$getEditor()?.chain().focus().setTextColor('{$colorKey}').run()")
            ->extraAttributes(['style' => "color: {$color}"]);
    }

    private static function textColorClearTool(): RichEditorTool
    {
        return RichEditorTool::make('textColorClear')
            ->label('清除颜色')
            ->icon(Heroicon::Minus)
            ->jsHandler("\$getEditor()?.chain().focus().unsetTextColor().run()");
    }

    /**
     * @return array<string, TextColor>
     */
    private static function editorTextColors(): array
    {
        return [
            'red' => TextColor::make('红色', '#dc2626', '#f87171'),
            'amber' => TextColor::make('橙色', '#d97706', '#fbbf24'),
            'green' => TextColor::make('绿色', '#16a34a', '#4ade80'),
            'blue' => TextColor::make('蓝色', '#2563eb', '#60a5fa'),
            'purple' => TextColor::make('紫色', '#9333ea', '#c084fc'),
        ];
    }
}
