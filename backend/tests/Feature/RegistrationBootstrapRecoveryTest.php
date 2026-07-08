<?php

// [IN]: Member registration submit API and cached race bootstrap API / 会员报名提交 API 与缓存赛事初始化 API
// [OUT]: Cross-browser latest submitted registration recovery assertions / 跨浏览器最近提交报名恢复断言
// [POS]: Backend bootstrap registration recovery feature test / 后端初始化报名恢复功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Enums\RaceStatus;
use App\Models\Member;
use App\Models\Pigeon;
use App\Models\PigeonLibrary;
use App\Models\Race;
use App\Models\RaceProject;
use App\Services\RaceCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RegistrationBootstrapRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_recovers_latest_submission_after_another_browser_submits(): void
    {
        [$member, $race, $single, $double, $firstPigeon, $secondPigeon] = $this->fixtures();
        app(RaceCacheService::class)->bootstrap($race->fresh(), $member->fresh());

        $this->actingAs($member, 'member')
            ->postJson("/api/member/races/{$race->id}/registrations", [
                'config_version' => $race->fresh()->config_version,
                'idempotency_key' => '11111111-1111-4111-8111-111111111111',
                'entries' => [
                    ['project_id' => $single->id, 'pigeon_ids' => [$firstPigeon->id]],
                    ['project_id' => $double->id, 'pigeon_ids' => [$firstPigeon->id, $secondPigeon->id]],
                ],
            ])
            ->assertOk();

        $this->actingAs($member, 'member')
            ->getJson("/api/member/races/{$race->id}/bootstrap")
            ->assertOk()
            ->assertJsonPath('existing_registration.entries.0.project_id', $single->id)
            ->assertJsonPath('existing_registration.entries.0.pigeons.0.pigeon_id', $firstPigeon->id)
            ->assertJsonPath('existing_registration.entries.1.project_id', $double->id)
            ->assertJsonPath('existing_registration.entries.1.pigeons.0.pigeon_id', $firstPigeon->id)
            ->assertJsonPath('existing_registration.entries.1.pigeons.1.pigeon_id', $secondPigeon->id);
    }

    public function test_idempotent_submit_path_also_clears_stale_bootstrap(): void
    {
        [$member, $race, $single, , $firstPigeon] = $this->fixtures();

        $this->actingAs($member, 'member')
            ->postJson("/api/member/races/{$race->id}/registrations", [
                'config_version' => $race->fresh()->config_version,
                'idempotency_key' => '22222222-2222-4222-8222-222222222222',
                'entries' => [
                    ['project_id' => $single->id, 'pigeon_ids' => [$firstPigeon->id]],
                ],
            ])
            ->assertOk();

        Cache::put("race:{$race->id}:version:{$race->fresh()->config_version}:member:{$member->id}:bootstrap", [
            'race' => ['id' => $race->id],
            'member' => ['id' => $member->id],
            'projects' => [],
            'pigeons' => [],
            'existing_registration' => null,
        ], 600);

        $this->actingAs($member, 'member')
            ->postJson("/api/member/races/{$race->id}/registrations", [
                'config_version' => $race->fresh()->config_version,
                'idempotency_key' => '22222222-2222-4222-8222-222222222222',
                'entries' => [
                    ['project_id' => $single->id, 'pigeon_ids' => [$firstPigeon->id]],
                ],
            ])
            ->assertOk();

        $this->actingAs($member, 'member')
            ->getJson("/api/member/races/{$race->id}/bootstrap")
            ->assertOk()
            ->assertJsonPath('existing_registration.entries.0.project_id', $single->id);
    }

    public function test_registration_rejects_pigeon_outside_project_library(): void
    {
        $member = Member::query()->create([
            'phone' => '13900002002',
            'password' => 'password',
            'loft_number' => 'A002',
            'participant_name' => '项目库鸽舍',
            'status' => 'enabled',
        ]);
        $allowedLibrary = PigeonLibrary::query()->create(['name' => '允许库', 'is_enabled' => true]);
        $otherLibrary = PigeonLibrary::query()->create(['name' => '其他库', 'is_enabled' => true]);
        $race = Race::query()->create([
            'name' => '项目库校验赛',
            'registration_start_at' => now()->subHour(),
            'registration_end_at' => now()->addHour(),
            'status' => RaceStatus::Published,
            'is_visible' => true,
            'require_admin_confirm' => false,
        ]);
        $project = RaceProject::query()->create([
            'race_id' => $race->id,
            'pigeon_library_id' => $allowedLibrary->id,
            'name' => '单羽 50',
            'group_size' => 1,
            'price_cent' => 5000,
            'sort_order' => 1,
            'is_enabled' => true,
        ]);
        $pigeon = Pigeon::query()->create([
            'pigeon_library_id' => $otherLibrary->id,
            'member_id' => $member->id,
            'loft_number' => $member->loft_number,
            'participant_name' => $member->participant_name,
            'ring_number' => '2026-13-000003',
            'status' => 'normal',
        ]);

        $this->actingAs($member, 'member')
            ->postJson("/api/member/races/{$race->id}/registrations", [
                'config_version' => $race->fresh()->config_version,
                'idempotency_key' => '33333333-3333-4333-8333-333333333333',
                'entries' => [
                    ['project_id' => $project->id, 'pigeon_ids' => [$pigeon->id]],
                ],
            ])
            ->assertForbidden()
            ->assertJsonPath('error_code', 'pigeon_not_owned');
    }

    private function fixtures(): array
    {
        $library = PigeonLibrary::default();
        $member = Member::query()->create([
            'phone' => '13900002001',
            'password' => 'password',
            'loft_number' => 'A001',
            'participant_name' => '跨浏览器鸽舍',
            'status' => 'enabled',
        ]);
        $race = Race::query()->create([
            'name' => '跨浏览器恢复赛',
            'registration_start_at' => now()->subHour(),
            'registration_end_at' => now()->addHour(),
            'status' => RaceStatus::Published,
            'is_visible' => true,
            'require_admin_confirm' => false,
        ]);
        $single = RaceProject::query()->create([
            'race_id' => $race->id,
            'pigeon_library_id' => $library->id,
            'name' => '单羽 50',
            'group_size' => 1,
            'price_cent' => 5000,
            'sort_order' => 1,
            'is_enabled' => true,
        ]);
        $double = RaceProject::query()->create([
            'race_id' => $race->id,
            'pigeon_library_id' => $library->id,
            'name' => '双羽组 200',
            'group_size' => 2,
            'price_cent' => 20000,
            'sort_order' => 2,
            'is_enabled' => true,
        ]);
        $firstPigeon = $this->pigeon($member, $library, '2026-13-000001');
        $secondPigeon = $this->pigeon($member, $library, '2026-13-000002');

        return [$member, $race, $single, $double, $firstPigeon, $secondPigeon];
    }

    private function pigeon(Member $member, PigeonLibrary $library, string $ring): Pigeon
    {
        return Pigeon::query()->create([
            'pigeon_library_id' => $library->id,
            'member_id' => $member->id,
            'loft_number' => $member->loft_number,
            'participant_name' => $member->participant_name,
            'ring_number' => $ring,
            'status' => 'normal',
        ]);
    }
}
