<?php
// [IN]: Filament dashboard page and admin resource URL helpers / Filament 仪表板页面与后台资源 URL 辅助
// [OUT]: Custom admin dashboard feature-card navigation / 自定义后台仪表板功能卡片导航
// [POS]: Backend admin dashboard landing page / 后端后台仪表板落地页
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Filament\Pages;

use App\Filament\Resources\MemberResource;
use App\Filament\Resources\PigeonResource;
use App\Filament\Resources\RaceProjectResource;
use App\Filament\Resources\RaceResource;
use App\Filament\Resources\RegistrationResource;
use App\Filament\Resources\InformationPostResource;
use Filament\Pages\Dashboard;

class AdminDashboard extends Dashboard
{
    protected static ?string $title = "仪表板";
    protected static ?string $navigationLabel = "仪表板";
    protected static string|\BackedEnum|null $navigationIcon = "heroicon-o-home";
    protected string $view = "filament.pages.admin-dashboard";

    /**
     * @return array<int, array{label: string, description: string, icon: string, href: string, accent: string}>
     */
    public function featureCards(): array
    {
        return [
            [
                "label" => "会员管理",
                "description" => "维护会员档案、登录手机号、棚号与参赛名",
                "icon" => "heroicon-o-user-group",
                "href" => MemberResource::getUrl("index"),
                "accent" => "oklch(0.72 0.14 164)",
            ],
            [
                "label" => "足环管理",
                "description" => "录入、批量导入和核对会员名下足环",
                "icon" => "heroicon-o-identification",
                "href" => PigeonResource::getUrl("index"),
                "accent" => "oklch(0.74 0.13 196)",
            ],
            [
                "label" => "报名项目",
                "description" => "配置单羽、多羽组、金额、规则",
                "icon" => "heroicon-o-squares-2x2",
                "href" => RaceProjectResource::getUrl("index"),
                "accent" => "oklch(0.78 0.13 92)",
            ],
            [
                "label" => "赛事管理",
                "description" => "管理赛事、报名时间",
                "icon" => "heroicon-o-flag",
                "href" => RaceResource::getUrl("index"),
                "accent" => "oklch(0.69 0.16 38)",
            ],
            [
                "label" => "报名记录",
                "description" => "查看报名明细、确认报名并导出 Excel",
                "icon" => "heroicon-o-clipboard-document-check",
                "href" => RegistrationResource::getUrl("index"),
                "accent" => "oklch(0.7 0.16 145)",
            ],
            [
                "label" => "品牌设置",
                "description" => "设置登录页赛事品牌 Logo",
                "icon" => "heroicon-o-photo",
                "href" => BrandSettings::getUrl(),
                "accent" => "oklch(0.72 0.12 285)",
            ],
            [
                "label" => "信息发布",
                "description" => "发布赛事规程、成绩与通知公告",
                "icon" => "heroicon-o-newspaper",
                "href" => InformationPostResource::getUrl("index"),
                "accent" => "oklch(0.73 0.14 210)",
            ],
        ];
    }
}
