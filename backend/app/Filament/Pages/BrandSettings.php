<?php

// [IN]: Filament form state, public storage disk, supported logo image types, and app settings / Filament 表单状态、公开存储磁盘、受支持 Logo 图片类型与应用设置
// [OUT]: Persisted readable public brand logo path for member H5 rendering / 已持久化、可供会员 H5 渲染的公开品牌 Logo 路径
// [POS]: Backend admin brand settings page / 后端后台品牌设置页
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Pages;

use App\Enums\CurrencyCode;
use App\Models\AppSetting;
use App\Models\User;
use App\Support\AdminPermissions;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
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
    protected static ?string $title = null;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = null;

    protected static string|\UnitEnum|null $navigationGroup = null;

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
            'registration_default_currency' => AppSetting::defaultRegistrationCurrency()->value,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('brand_logo_path')
                    ->label(__('会员端登录页 Logo'))
                    ->disk('public')
                    ->directory('branding')
                    ->visibility('public')
                    ->image()
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/avif', 'image/svg+xml'])
                    ->maxSize(2048)
                    ->helperText(__('支持 PNG、JPG、JPEG、WebP、GIF、AVIF、SVG，建议使用透明背景或横向 Logo。')),
                Select::make('registration_default_currency')
                    ->label(__('新赛事默认报名币种'))
                    ->options([
                        CurrencyCode::CNY->value => CurrencyCode::CNY->label(),
                        CurrencyCode::TWD->value => CurrencyCode::TWD->label(),
                    ])
                    ->required()
                    ->helperText(__('只影响之后新建的赛事；已存在赛事和历史报名不会改变。')),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('品牌 Logo 与赛事默认设置'))
                ->description(__('Logo 用于会员登录页；默认报名币种只用于新建赛事，已存在赛事不会随设置变化。'))
                ->schema([
                    Form::make([EmbeddedSchema::make('form')])
                        ->id('form')
                        ->livewireSubmitHandler('save')
                        ->footer([
                            Actions::make([
                                Action::make('save')
                                    ->label(__('保存设置'))
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
        AppSetting::putValue(
            AppSetting::REGISTRATION_DEFAULT_CURRENCY,
            CurrencyCode::fromValue($data['registration_default_currency'] ?? null)->value,
        );

        Notification::make()
            ->title(__('品牌设置已保存'))
            ->success()
            ->send();
    }

    public function getTitle(): string
    {
        return __('品牌设置');
    }

    public static function getNavigationLabel(): string
    {
        return __('品牌设置');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('系统设置');
    }
}
