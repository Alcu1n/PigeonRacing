<?php

// [IN]: Backend module and action identifiers / 后台模块与操作标识
// [OUT]: Stable administrator permission catalog / 稳定的管理员权限目录
// [POS]: Shared authorization vocabulary / 共享授权词汇
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Support;

final class AdminPermissions
{
    /** @var array<string, string> */
    public const MODULES = [
        'members' => '会员管理',
        'pigeon-libraries' => '足环库管理',
        'pigeons' => '足环管理',
        'ring-sales' => '售环记录',
        'races' => '赛事管理',
        'race-projects' => '报名项目',
        'registration-categories' => '递进报名类别',
        'registrations' => '报名记录',
        'information-posts' => '信息发布',
        'brand-settings' => '品牌设置',
    ];

    /** @var array<string, string> */
    public const ACTIONS = [
        'view' => '查看',
        'create' => '新增',
        'update' => '编辑',
        'delete' => '删除',
    ];

    /** @return array<int, string> */
    public static function all(): array
    {
        $permissions = [];

        foreach (array_keys(self::MODULES) as $module) {
            foreach (array_keys(self::ACTIONS) as $action) {
                $permissions[] = self::name($module, $action);
            }
        }

        return $permissions;
    }

    public static function name(string $module, string $action): string
    {
        return "{$module}.{$action}";
    }

    /** @return array<string, array{label: string, permissions: array<string, string>}> */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::MODULES as $module => $label) {
            $permissions = [];
            foreach (self::ACTIONS as $action => $actionLabel) {
                $permissions[self::name($module, $action)] = $actionLabel;
            }

            $groups[$module] = ['label' => $label, 'permissions' => $permissions];
        }

        return $groups;
    }
}
