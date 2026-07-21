<?php

// [IN]: InformationPost model rows and rich HTML content / 信息发布模型行与富文本 HTML 内容
// [OUT]: Filament information publishing CRUD with TipTap RichEditor / 带 TipTap RichEditor 的 Filament 信息发布 CRUD
// [POS]: Backend admin information publishing resource / 后端后台信息发布资源
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Resources\InformationPostResource\Pages;
use App\Models\InformationPost;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\RichEditor\TextColor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Js;

class InformationPostResource extends Resource
{
    use HasModulePermissions;

    protected static string $permissionModule = 'information-posts';

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
                    self::textColorPaletteTool(),
                ])
                ->toolbarButtons([
                    [
                        'bold',
                        'italic',
                        'underline',
                        'strike',
                        'link',
                        'textColorPalette',
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

    private static function textColorPaletteTool(): RichEditorTool
    {
        return RichEditorTool::make('textColorPalette')
            ->label('文字颜色')
            ->icon(Heroicon::Swatch)
            ->activeKey('textColor')
            ->jsHandler(self::textColorPaletteJs());
    }

    /**
     * @return array<string, TextColor>
     */
    private static function editorTextColors(): array
    {
        return array_map(
            fn (array $color): TextColor => TextColor::make($color['label'], $color['color'], $color['darkColor']),
            self::editorColorOptions(),
        );
    }

    /**
     * @return array<string, array{label: string, color: string, darkColor: string}>
     */
    private static function editorColorOptions(): array
    {
        return [
            'slate' => ['label' => '墨灰', 'color' => '#334155', 'darkColor' => '#cbd5e1'],
            'gray' => ['label' => '中灰', 'color' => '#4b5563', 'darkColor' => '#d1d5db'],
            'red' => ['label' => '正红', 'color' => '#dc2626', 'darkColor' => '#f87171'],
            'rose' => ['label' => '玫红', 'color' => '#e11d48', 'darkColor' => '#fb7185'],
            'orange' => ['label' => '橙色', 'color' => '#ea580c', 'darkColor' => '#fb923c'],
            'amber' => ['label' => '琥珀', 'color' => '#d97706', 'darkColor' => '#fbbf24'],
            'yellow' => ['label' => '黄色', 'color' => '#ca8a04', 'darkColor' => '#fde047'],
            'lime' => ['label' => '青柠', 'color' => '#65a30d', 'darkColor' => '#bef264'],
            'green' => ['label' => '绿色', 'color' => '#16a34a', 'darkColor' => '#4ade80'],
            'emerald' => ['label' => '翠绿', 'color' => '#059669', 'darkColor' => '#34d399'],
            'teal' => ['label' => '蓝绿', 'color' => '#0d9488', 'darkColor' => '#2dd4bf'],
            'cyan' => ['label' => '青色', 'color' => '#0891b2', 'darkColor' => '#22d3ee'],
            'sky' => ['label' => '天蓝', 'color' => '#0284c7', 'darkColor' => '#38bdf8'],
            'blue' => ['label' => '蓝色', 'color' => '#2563eb', 'darkColor' => '#60a5fa'],
            'indigo' => ['label' => '靛蓝', 'color' => '#4f46e5', 'darkColor' => '#818cf8'],
            'violet' => ['label' => '紫罗兰', 'color' => '#7c3aed', 'darkColor' => '#a78bfa'],
            'purple' => ['label' => '紫色', 'color' => '#9333ea', 'darkColor' => '#c084fc'],
            'fuchsia' => ['label' => '紫红', 'color' => '#c026d3', 'darkColor' => '#e879f9'],
            'pink' => ['label' => '粉色', 'color' => '#db2777', 'darkColor' => '#f472b6'],
        ];
    }

    private static function textColorPaletteJs(): string
    {
        $colors = array_map(
            fn (string $key, array $color): array => [
                'key' => $key,
                'label' => $color['label'],
                'color' => $color['color'],
            ],
            array_keys(self::editorColorOptions()),
            self::editorColorOptions(),
        );

        $colorsJson = Js::from($colors)->toHtml();

        return <<<JS
            (() => {
                const button = \$event.currentTarget
                const editor = \$getEditor()

                if (! button || ! editor) {
                    return
                }

                \$event.preventDefault()
                \$event.stopPropagation()

                const doc = button.ownerDocument
                const win = doc.defaultView || window
                const anchor = button.dataset.colorPaletteAnchor || (win.crypto?.randomUUID?.() ?? ('palette-' + Date.now().toString(36) + Math.random().toString(36).slice(2)))

                button.dataset.colorPaletteAnchor = anchor

                const wasSameAnchor = win.pigeonTextColorPaletteAnchor === anchor

                if (typeof win.pigeonTextColorPaletteCleanup === 'function') {
                    win.pigeonTextColorPaletteCleanup()
                }

                if (wasSameAnchor) {
                    return
                }

                const colors = {$colorsJson}
                const panel = doc.createElement('div')
                const grid = doc.createElement('div')
                const footer = doc.createElement('div')
                const clearButton = doc.createElement('button')

                panel.setAttribute('data-pigeon-text-color-palette', 'true')
                panel.style.position = 'fixed'
                panel.style.zIndex = '99999'
                panel.style.padding = '10px'
                panel.style.border = '1px solid rgba(148, 163, 184, 0.28)'
                panel.style.borderRadius = '12px'
                panel.style.background = 'rgba(17, 24, 39, 0.98)'
                panel.style.boxShadow = '0 18px 40px rgba(0, 0, 0, 0.36)'
                panel.style.width = '224px'

                grid.style.display = 'grid'
                grid.style.gridTemplateColumns = 'repeat(7, 1fr)'
                grid.style.gap = '8px'

                footer.style.display = 'flex'
                footer.style.justifyContent = 'flex-end'
                footer.style.marginTop = '10px'
                footer.style.paddingTop = '8px'
                footer.style.borderTop = '1px solid rgba(148, 163, 184, 0.18)'

                const applyColor = (color) => {
                    const chain = editor.chain().focus()

                    if (editor.state.selection.empty && editor.isActive('textColor')) {
                        chain.extendMarkRange('textColor')
                    }

                    chain.setTextColor({ color }).run()
                    close()
                }

                const clearColor = () => {
                    const chain = editor.chain().focus()

                    if (editor.state.selection.empty && editor.isActive('textColor')) {
                        chain.extendMarkRange('textColor')
                    }

                    chain.unsetTextColor().run()
                    close()
                }

                colors.forEach((color) => {
                    const item = doc.createElement('button')

                    item.type = 'button'
                    item.title = color.label
                    item.setAttribute('aria-label', color.label)
                    item.style.width = '22px'
                    item.style.height = '22px'
                    item.style.borderRadius = '999px'
                    item.style.border = '2px solid rgba(255, 255, 255, 0.88)'
                    item.style.background = color.color
                    item.style.boxShadow = '0 0 0 1px rgba(15, 23, 42, 0.24)'
                    item.style.cursor = 'pointer'

                    item.addEventListener('mousedown', (event) => event.preventDefault())
                    item.addEventListener('click', (event) => {
                        event.preventDefault()
                        event.stopPropagation()
                        applyColor(color.key)
                    })

                    grid.appendChild(item)
                })

                clearButton.type = 'button'
                clearButton.textContent = '清除颜色'
                clearButton.style.border = '0'
                clearButton.style.borderRadius = '8px'
                clearButton.style.padding = '6px 10px'
                clearButton.style.background = 'rgba(148, 163, 184, 0.16)'
                clearButton.style.color = '#e5e7eb'
                clearButton.style.fontSize = '12px'
                clearButton.style.fontWeight = '700'
                clearButton.style.cursor = 'pointer'
                clearButton.addEventListener('mousedown', (event) => event.preventDefault())
                clearButton.addEventListener('click', (event) => {
                    event.preventDefault()
                    event.stopPropagation()
                    clearColor()
                })

                footer.appendChild(clearButton)
                panel.appendChild(grid)
                panel.appendChild(footer)
                doc.body.appendChild(panel)

                const rect = button.getBoundingClientRect()
                const left = Math.max(8, Math.min(rect.left, win.innerWidth - panel.offsetWidth - 8))
                const top = Math.max(8, Math.min(rect.bottom + 8, win.innerHeight - panel.offsetHeight - 8))

                panel.style.left = left + 'px'
                panel.style.top = top + 'px'

                const outsideHandler = (event) => {
                    if (panel.contains(event.target) || button.contains(event.target)) {
                        return
                    }

                    close()
                }

                const keyHandler = (event) => {
                    if (event.key === 'Escape') {
                        close()
                    }
                }

                function close() {
                    doc.removeEventListener('click', outsideHandler, true)
                    doc.removeEventListener('keydown', keyHandler, true)
                    panel.remove()

                    if (win.pigeonTextColorPaletteAnchor === anchor) {
                        win.pigeonTextColorPaletteAnchor = null
                        win.pigeonTextColorPaletteCleanup = null
                    }
                }

                win.pigeonTextColorPaletteAnchor = anchor
                win.pigeonTextColorPaletteCleanup = close

                setTimeout(() => {
                    doc.addEventListener('click', outsideHandler, true)
                    doc.addEventListener('keydown', keyHandler, true)
                }, 0)
            })()
            JS;
    }
}
