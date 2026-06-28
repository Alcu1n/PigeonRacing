<?php
// [IN]: Registration submission workflow and persisted race/member rows / 报名提交流程与持久化赛事/会员行
// [OUT]: Short readable registration number assertion / 短可读报名编号断言
// [POS]: Backend registration number feature test / 后端报名编号功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Enums\RaceStatus;
use App\Models\Member;
use App\Models\Pigeon;
use App\Models\Race;
use App\Models\RaceProject;
use App\Services\RegistrationSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationShortNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_uses_short_race_and_loft_registration_number(): void
    {
        $member = Member::query()->create([
            'phone' => '13900001001',
            'password' => 'password',
            'loft_number' => 'A001',
            'participant_name' => '短号鸽舍',
            'status' => 'enabled',
        ]);
        $race = Race::query()->create([
            'name' => '短号测试赛',
            'registration_start_at' => now()->subHour(),
            'registration_end_at' => now()->addHour(),
            'status' => RaceStatus::Published,
            'is_visible' => true,
            'require_admin_confirm' => false,
        ]);
        $project = RaceProject::query()->create([
            'race_id' => $race->id,
            'name' => '单羽 50',
            'group_size' => 1,
            'price_cent' => 5000,
            'is_enabled' => true,
        ]);
        $pigeon = Pigeon::query()->create([
            'member_id' => $member->id,
            'loft_number' => $member->loft_number,
            'participant_name' => $member->participant_name,
            'ring_number' => '2026-13-000001',
            'status' => 'normal',
        ]);

        $race = $race->fresh();
        $registration = app(RegistrationSubmissionService::class)->submit($member, $race, $race->config_version, '11111111-1111-4111-8111-111111111111', [
            ['project_id' => $project->id, 'pigeon_ids' => [$pigeon->id]],
        ]);

        $this->assertSame("R{$race->id}-A001", $registration->registration_no);
        $this->assertLessThanOrEqual(10, strlen($registration->registration_no));
    }
}
