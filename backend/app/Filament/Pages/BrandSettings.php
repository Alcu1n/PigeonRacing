<?php

// [IN]: Filament form state, public storage disk, supported logo image types, and app settings / Filament 表单状态、公开存储磁盘、受支持 Logo 图片类型与应用设置
// [OUT]: Persisted readable public brand logo path for member H5 rendering / 已持久化、可供会员 H5 渲染的公开品牌 Logo 路径
// [POS]: Backend admin brand settings page / 后端后台品牌设置页
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Models\User;
use App\Support\AdminPermissions;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class BrandSettings extends Page
{
    protected static ?string $title = '品牌设置';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = '品牌设置';

    protected static string|\UnitEnum|null $navigationGroup = '系统设置';

    protected static ?int $navigationSort = 90;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can(AdminPermissions::name('brand-settings', 'view'));
    }

    public function mount(): void
    {
        $this->form->fill([
            'brand_logo_path' => AppSetting::getValue(AppSetting::BRAND_LOGO_PATH),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('brand_logo_path')
                    ->label('会员端登录页 Logo')
                    ->disk('public')
                    ->directory('branding')
                    ->visibility('public')
                    ->image()
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/avif', 'image/svg+xml'])
                    ->maxSize(2048)
                    ->helperText('支持 PNG、JPG、JPEG、WebP、GIF、AVIF、SVG，建议使用透明背景或横向 Logo。'),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('品牌 Logo')
                ->description('上传后会显示在会员登录页顶部；未上传时只显示居中的页面标题。')
                ->schema([
                    Form::make([EmbeddedSchema::make('form')])
                        ->id('form')
                        ->livewireSubmitHandler('save')
                        ->footer([
                            Actions::make([
                                Action::make('save')
                                    ->label('保存设置')
                                    ->submit('save'),
                            ]),
                        ]),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can(AdminPermissions::name('brand-settings', 'update')), 403);

        $data = $this->form->getState();
        $path = $data['brand_logo_path'] ?? null;

        if ($path) {
            Storage::disk('public')->setVisibility($path, 'public');
        }

        AppSetting::putValue(AppSetting::BRAND_LOGO_PATH, $path);

        Notification::make()
            ->title('品牌设置已保存')
            ->success()
            ->send();
    }
}
