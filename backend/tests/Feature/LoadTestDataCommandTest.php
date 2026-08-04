<?php

// [IN]: Load-test Artisan commands and isolated database / 压测 Artisan 命令与隔离数据库
// [OUT]: Provision, retention preview, credential hashing, and explicit cleanup assertions / 准备、保留预览、凭据哈希与显式清理断言
// [POS]: Production load-test workflow feature test / 生产压测流程功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Pigeon;
use App\Models\Race;
use App\Models\RaceProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoadTestDataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_provision_creates_marked_data_and_cleanup_requires_force(): void
    {
        $password = 'load-test-password';

        $exitCode = Artisan::call('load-test:provision', [
            '--count' => 3,
            '--submitters' => 2,
            '--phone-prefix' => '1998000',
            '--password' => $password,
            '--run-id' => 'feature-test-run',
        ]);

        $this->assertSame(0, $exitCode);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('feature-test-run', $payload['run_id']);
        $this->assertCount(3, $payload['members']);
        $this->assertCount(2, $payload['submitter_indexes']);
        $this->assertTrue(Hash::check($password, Member::query()->firstOrFail()->password));
        $this->assertSame(3, Member::query()->where('remark', '[load-test:feature-test-run]')->count());
        $this->assertSame(3, Pigeon::query()->whereHas('member', fn ($query) => $query->where('remark', '[load-test:feature-test-run]'))->count());

        $race = Race::query()->where('name', '[压测] feature-test-run')->firstOrFail();
        $project = RaceProject::query()->where('race_id', $race->id)->firstOrFail();
        $this->assertSame(1, $project->group_size);
        $this->assertGreaterThan(1, $race->config_version);

        $previewExitCode = Artisan::call('load-test:cleanup', ['runId' => 'feature-test-run']);
        $this->assertSame(0, $previewExitCode);
        $this->assertStringContainsString('"mode": "preview"', Artisan::output());
        $this->assertDatabaseHas('races', ['id' => $race->id]);

        $cleanupExitCode = Artisan::call('load-test:cleanup', [
            'runId' => 'feature-test-run',
            '--force' => true,
        ]);
        $this->assertSame(0, $cleanupExitCode);
        $this->assertStringContainsString('"mode": "deleted"', Artisan::output());
        $this->assertDatabaseMissing('races', ['id' => $race->id]);
        $this->assertDatabaseMissing('members', ['remark' => '[load-test:feature-test-run]']);
        $this->assertDatabaseMissing('pigeons', ['ring_number' => $payload['members'][0]['pigeon_ring_number']]);
    }
}
