<?php
// [IN]: Registration submission service and in-memory fixtures / 报名提交服务与内存夹具
// [OUT]: Rule validation assertions / 规则校验断言
// [POS]: Backend registration rule unit tests / 后端报名规则单元测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Unit;

use App\Models\Pigeon;
use App\Models\RaceProject;
use App\Services\RaceCacheService;
use App\Services\RegistrationRuleException;
use App\Services\RegistrationSubmissionService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class RegistrationSubmissionServiceTest extends TestCase
{
    public function test_it_recalculates_total_from_project_prices(): void
    {
        $service = new RegistrationSubmissionService($this->createMock(RaceCacheService::class));
        $result = $service->validateEntries([
            ['project_id' => 1, 'pigeon_ids' => [101]],
            ['project_id' => 2, 'pigeon_ids' => [101, 102]],
        ], $this->projects(), $this->pigeons());

        $this->assertSame(25000, $result['total_amount_cent']);
    }

    public function test_it_rejects_group_size_mismatch(): void
    {
        $this->expectException(RegistrationRuleException::class);
        $this->expectExceptionMessage('必须选择 2 羽');

        $service = new RegistrationSubmissionService($this->createMock(RaceCacheService::class));
        $service->validateEntries([
            ['project_id' => 2, 'pigeon_ids' => [101]],
        ], $this->projects(), $this->pigeons());
    }

    public function test_it_rejects_unowned_pigeon(): void
    {
        $this->expectException(RegistrationRuleException::class);

        $service = new RegistrationSubmissionService($this->createMock(RaceCacheService::class));
        $service->validateEntries([
            ['project_id' => 1, 'pigeon_ids' => [888]],
        ], $this->projects(), $this->pigeons());
    }

    public function test_it_rejects_repeat_pigeon_when_project_disallows_repeat(): void
    {
        $this->expectException(RegistrationRuleException::class);
        $this->expectExceptionMessage('不允许同一足环重复');

        $service = new RegistrationSubmissionService($this->createMock(RaceCacheService::class));
        $service->validateEntries([
            ['project_id' => 1, 'pigeon_ids' => [101]],
            ['project_id' => 1, 'pigeon_ids' => [101]],
        ], $this->projects(), $this->pigeons());
    }

    public function test_it_allows_repeat_pigeon_when_project_allows_repeat(): void
    {
        $projects = $this->projects();
        $projects->get(2)->allow_repeat_pigeon_in_project = true;

        $service = new RegistrationSubmissionService($this->createMock(RaceCacheService::class));
        $result = $service->validateEntries([
            ['project_id' => 2, 'pigeon_ids' => [101, 102]],
            ['project_id' => 2, 'pigeon_ids' => [101, 999]],
        ], $projects, $this->pigeons());

        $this->assertSame(40000, $result['total_amount_cent']);
    }

    public function test_it_rejects_repeat_pigeon_when_max_usage_is_reached(): void
    {
        $this->expectException(RegistrationRuleException::class);
        $this->expectExceptionMessage('最大使用次数');

        $projects = $this->projects();
        $projects->get(2)->allow_repeat_pigeon_in_project = true;
        $projects->get(2)->max_usage_per_pigeon = 1;

        $service = new RegistrationSubmissionService($this->createMock(RaceCacheService::class));
        $service->validateEntries([
            ['project_id' => 2, 'pigeon_ids' => [101, 102]],
            ['project_id' => 2, 'pigeon_ids' => [101, 999]],
        ], $projects, $this->pigeons());
    }

    private function projects(): Collection
    {
        $single = new RaceProject(['name' => '单羽 50 元', 'group_size' => 1, 'price_cent' => 5000, 'allow_repeat_pigeon_in_project' => false, 'is_enabled' => true]);
        $double = new RaceProject(['name' => '双羽组 200 元', 'group_size' => 2, 'price_cent' => 20000, 'allow_repeat_pigeon_in_project' => false, 'is_enabled' => true]);
        $single->forceFill(['id' => 1]);
        $double->forceFill(['id' => 2]);

        return collect([1 => $single, 2 => $double]);
    }

    private function pigeons(): Collection
    {
        $first = new Pigeon(['ring_number' => 'CHN-2026-03-000101']);
        $second = new Pigeon(['ring_number' => 'CHN-2026-03-000102']);
        $third = new Pigeon(['ring_number' => 'CHN-2026-03-000999']);
        $first->forceFill(['id' => 101]);
        $second->forceFill(['id' => 102]);
        $third->forceFill(['id' => 999]);

        return collect([101 => $first, 102 => $second, 999 => $third]);
    }
}
