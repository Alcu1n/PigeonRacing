<?php

// [IN]: Empty database / 空数据库
// [OUT]: Demo admin, member, race, projects, and pigeons / 演示管理员、会员、赛事、项目与足环
// [POS]: Backend development seed entrypoint / 后端开发种子入口
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Database\Seeders;

use App\Enums\RaceStatus;
use App\Models\Member;
use App\Models\Pigeon;
use App\Models\Race;
use App\Models\RaceProject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => '系统管理员', 'password' => 'password']
        );
        if (Role::query()->where('name', 'super-admin')->where('guard_name', 'web')->exists()) {
            $admin->syncRoles(['super-admin']);
        }

        $member = Member::query()->firstOrCreate(
            ['phone' => '13800000000'],
            ['password' => 'password', 'loft_number' => 'A001', 'participant_name' => '张三鸽舍', 'status' => 'enabled']
        );

        for ($i = 1; $i <= 20; $i++) {
            Pigeon::query()->firstOrCreate(
                ['ring_number' => sprintf('CHN-2026-03-%06d', $i)],
                ['member_id' => $member->id, 'loft_number' => $member->loft_number, 'participant_name' => $member->participant_name, 'status' => 'normal']
            );
        }

        $race = Race::query()->firstOrCreate(
            ['name' => '2026 春季大奖赛'],
            [
                'description' => '手机端在线报名演示赛事',
                'registration_start_at' => now()->subDay(),
                'registration_end_at' => now()->addDays(7),
                'status' => RaceStatus::Published,
                'config_version' => 1,
                'allow_member_edit' => true,
                'require_admin_confirm' => true,
                'is_visible' => true,
            ]
        );

        foreach ([['单羽 50 元', 1, 5000], ['单羽 100 元', 1, 10000], ['单羽 200 元', 1, 20000], ['双羽组 200 元', 2, 20000], ['三羽组 300 元', 3, 30000], ['五羽组 500 元', 5, 50000]] as $index => [$name, $size, $price]) {
            RaceProject::query()->firstOrCreate(
                ['race_id' => $race->id, 'name' => $name],
                ['group_size' => $size, 'price_cent' => $price, 'sort_order' => $index + 1, 'is_enabled' => true]
            );
        }
    }
}
