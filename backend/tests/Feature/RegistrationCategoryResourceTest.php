<?php

// [IN]: Filament admin session and progressive category import route / Filament 后台会话与递进类别导入路由
// [OUT]: Progressive first-stage import page render assertions / 递进第一阶段导入页渲染断言
// [POS]: Backend progressive category resource feature test / 后端递进类别资源功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Enums\RaceStatus;
use App\Filament\Resources\RegistrationCategoryResource;
use App\Models\Member;
use App\Models\Pigeon;
use App\Models\Race;
use App\Models\RaceProject;
use App\Models\RegistrationCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationCategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_progressive_first_stage_import_page(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);
        $admin->assignRole('super-admin');
        $race = Race::query()->create([
            'name' => '测试赛事',
            'registration_start_at' => now()->subDay(),
            'registration_end_at' => now()->addDay(),
            'status' => RaceStatus::Published,
            'is_visible' => true,
        ]);
        $category = RegistrationCategory::query()->create([
            'race_id' => $race->id,
            'name' => '站站赛',
            'is_enabled' => true,
        ]);
        RaceProject::query()->create([
            'race_id' => $race->id,
            'project_type' => RaceProject::TYPE_PROGRESSIVE_STAGE,
            'registration_category_id' => $category->id,
            'stage_order' => 1,
            'name' => '龙湾站 1.5K',
            'group_size' => 1,
            'price_cent' => 150000,
            'sort_order' => 1,
            'is_enabled' => true,
        ]);

        $this->actingAs($admin)
            ->get(RegistrationCategoryResource::getUrl('import-first-stage', ['record' => $category->getKey()]))
            ->assertOk()
            ->assertSee('上传 Excel')
            ->assertSee('下载模板')
            ->assertSee('序号、会员棚号、会员参赛名、足环号码、')
            ->assertSee('龙湾站 1.5K');
    }

    public function test_admin_can_open_progressive_stage_data_page(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin-stage-data@example.com',
            'password' => 'password',
        ]);
        $admin->assignRole('super-admin');
        $race = Race::query()->create([
            'name' => '测试赛事',
            'registration_start_at' => now()->subDay(),
            'registration_end_at' => now()->addDay(),
            'status' => RaceStatus::Published,
            'is_visible' => true,
        ]);
        $category = RegistrationCategory::query()->create([
            'race_id' => $race->id,
            'name' => '站站赛',
            'is_enabled' => true,
        ]);
        RaceProject::query()->create([
            'race_id' => $race->id,
            'project_type' => RaceProject::TYPE_PROGRESSIVE_STAGE,
            'registration_category_id' => $category->id,
            'stage_order' => 1,
            'name' => '龙湾站 1.5K',
            'group_size' => 1,
            'price_cent' => 150000,
            'sort_order' => 1,
            'is_enabled' => true,
        ]);
        $member = Member::query()->create([
            'phone' => '13900000999',
            'password' => 'password',
            'loft_number' => 'A001',
            'participant_name' => '张三鸽舍',
            'status' => 'enabled',
        ]);
        Pigeon::query()->create([
            'member_id' => $member->id,
            'loft_number' => $member->loft_number,
            'participant_name' => $member->participant_name,
            'ring_number' => '2026-13-000001',
            'status' => 'normal',
        ]);

        $this->actingAs($admin)
            ->get(RegistrationCategoryResource::getUrl('stage-data', ['record' => $category->getKey(), 'member_id' => $member->id]))
            ->assertOk()
            ->assertSee('阶段数据管理')
            ->assertSee('龙湾站 1.5K')
            ->assertSee('保存阶段数据');
    }
}
