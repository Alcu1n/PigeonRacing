<?php

// [IN]: Filament dashboard page and admin resource URL helpers / Filament 仪表板页面与后台资源 URL 辅助
// [OUT]: Custom admin dashboard feature-card navigation / 自定义后台仪表板功能卡片导航
// [POS]: Backend admin dashboard landing page / 后端后台仪表板落地页
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Pages;

use App\Filament\Resources\AdminUserResource;
use App\Filament\Resources\InformationPostResource;
use App\Filament\Resources\MemberResource;
use App\Filament\Resources\PigeonLibraryResource;
use App\Filament\Resources\PigeonResource;
use App\Filament\Resources\RaceProjectResource;
use App\Filament\Resources\RaceResource;
use App\Filament\Resources\RegistrationCategoryResource;
use App\Filament\Resources\RegistrationResource;
use App\Filament\Resources\RingSaleResource;
use Filament\Pages\Dashboard;

class AdminDashboard extends Dashboard
{
    protected static ?string $title = null;

    protected static ?string $navigationLabel = null;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected string $view = 'filament.pages.admin-dashboard';

    public function getTitle(): string
    {
        return __('仪表板');
    }

    public static function getNavigationLabel(): string
    {
        return __('仪表板');
    }

    /**
     * @return array<int, array{label: string, description: string, icon: string, href: string, accent: string}>
     */
    public function featureCards(): array
    {
        $cards = [
            [
                'label' => __('会员管理'),
                'description' => __('维护会员档案、登录手机号、棚号与参赛名'),
                'icon' => 'heroicon-o-user-group',
                'href' => MemberResource::getUrl('index'),
                'accent' => 'oklch(0.72 0.14 164)',
                'visible' => MemberResource::canViewAny(),
            ],
            [
                'label' => __('足环库管理'),
                'description' => __('维护全局足环库与启用状态'),
                'icon' => 'heroicon-o-archive-box',
                'href' => PigeonLibraryResource::getUrl('index'),
                'accent' => 'oklch(0.73 0.13 260)',
                'visible' => PigeonLibraryResource::canViewAny(),
            ],
            [
                'label' => __('足环管理'),
                'description' => __('录入、批量导入和核对会员名下足环'),
                'icon' => 'heroicon-o-identification',
                'href' => PigeonResource::getUrl('index'),
                'accent' => 'oklch(0.74 0.13 196)',
                'visible' => PigeonResource::canViewAny(),
            ],
            [
                'label' => __('售环记录'),
                'description' => __('售环快速录入、收款与台账管理'),
                'icon' => 'heroicon-o-shopping-bag',
                'href' => RingSaleResource::getUrl('index'),
                'accent' => 'oklch(0.75 0.14 60)',
                'visible' => RingSaleResource::canViewAny(),
            ],
            [
                'label' => __('赛事管理'),
                'description' => __('管理赛事、报名时间'),
                'icon' => 'heroicon-o-flag',
                'href' => RaceResource::getUrl('index'),
                'accent' => 'oklch(0.69 0.16 38)',
                'visible' => RaceResource::canViewAny(),
            ],
            [
                'label' => __('报名项目'),
                'description' => __('配置单羽、多羽组、阶段项目、金额、规则'),
                'icon' => 'heroicon-o-squares-2x2',
                'href' => RaceProjectResource::getUrl('index'),
                'accent' => 'oklch(0.78 0.13 92)',
                'visible' => RaceProjectResource::canViewAny(),
            ],
            [
                'label' => __('递进报名类别'),
                'description' => __('配置站站赛、月月赛与当前开放阶段'),
                'icon' => 'heroicon-o-arrow-path-rounded-square',
                'href' => RegistrationCategoryResource::getUrl('index'),
                'accent' => 'oklch(0.74 0.15 128)',
                'visible' => RegistrationCategoryResource::canViewAny(),
            ],
            [
                'label' => __('报名记录'),
                'description' => __('查看报名明细、确认报名并导出 Excel'),
                'icon' => 'heroicon-o-clipboard-document-check',
                'href' => RegistrationResource::getUrl('index'),
                'accent' => 'oklch(0.7 0.16 145)',
                'visible' => RegistrationResource::canViewAny(),
            ],
            [
                'label' => __('信息发布'),
                'description' => __('发布赛事规程、成绩与通知公告'),
                'icon' => 'heroicon-o-newspaper',
                'href' => InformationPostResource::getUrl('index'),
                'accent' => 'oklch(0.73 0.14 210)',
                'visible' => InformationPostResource::canViewAny(),
            ],
            [
                'label' => __('权限管理'),
                'description' => __('管理员账号与模块权限分配'),
                'icon' => 'heroicon-o-key',
                'href' => AdminUserResource::getUrl('index'),
                'accent' => 'oklch(0.71 0.13 250)',
                'visible' => AdminUserResource::canViewAny(),
            ],
            [
                'label' => __('品牌设置'),
                'description' => __('设置登录页赛事品牌 Logo'),
                'icon' => 'heroicon-o-photo',
                'href' => BrandSettings::getUrl(),
                'accent' => 'oklch(0.72 0.12 285)',
                'visible' => BrandSettings::canAccess(),
            ],
            [
                'label' => __('使用教程'),
                'description' => __('后台各模块操作流程与上手指引'),
                'icon' => 'heroicon-o-book-open',
                'href' => UsageTutorial::getUrl(),
                'accent' => 'oklch(0.73 0.12 175)',
                'visible' => true,
            ],
        ];

        return array_values(array_map(function (array $card): array {
            unset($card['visible']);

            return $card;
        }, array_filter($cards, fn (array $card): bool => $card['visible'])));
    }
}
