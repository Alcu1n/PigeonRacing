<?php

// [IN]: Explicit load-test run identifier and guarded production rows / 明确的压测运行标识与受保护生产数据行
// [OUT]: Preview or manual deletion of one synthetic dataset / 一个合成数据集的预览或手动删除
// [POS]: Manual load-test data cleanup command / 手动压测数据清理命令
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\Pigeon;
use App\Models\Race;
use App\Models\Registration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CleanupLoadTestData extends Command
{
    protected $signature = 'load-test:cleanup
        {runId : The exact run identifier returned by load-test:provision}
        {--force : Actually delete the guarded synthetic dataset}';

    protected $description = 'Preview or manually remove one retained synthetic load-test dataset.';

    public function handle(): int
    {
        $runId = trim((string) $this->argument('runId'));

        if (! preg_match('/^[A-Za-z0-9_-]{1,48}$/', $runId)) {
            $this->error('runId 只能包含字母、数字、下划线和短横线，长度不超过 48。');

            return self::FAILURE;
        }

        $marker = "[load-test:{$runId}]";
        $race = Race::query()->where('name', "[压测] {$runId}")->first();
        $members = Member::query()->where('remark', $marker)->orderBy('id')->get();
        $memberIds = $members->modelKeys();

        if (! $race && $members->isEmpty()) {
            $this->error("没有找到运行 {$runId} 的压测赛事或会员数据。");

            return self::FAILURE;
        }

        $raceRegistrationCount = $race?->registrations()->count() ?? 0;
        $foreignRaceRegistrationCount = $race
            ? Registration::query()
                ->where('race_id', $race->id)
                ->whereNotIn('member_id', $memberIds ?: [-1])
                ->count()
            : 0;
        $outsideRegistrationCount = $memberIds === []
            ? 0
            : Registration::query()
                ->whereIn('member_id', $memberIds)
                ->when($race, fn ($query) => $query->where('race_id', '!=', $race->id))
                ->count();
        $pigeonCount = $memberIds === [] ? 0 : Pigeon::query()->whereIn('member_id', $memberIds)->count();

        if ($foreignRaceRegistrationCount > 0 || $outsideRegistrationCount > 0) {
            $this->error('检测到压测数据与其他会员或赛事发生关联，已停止清理；请人工核查。');

            return self::FAILURE;
        }

        $summary = [
            'run_id' => $runId,
            'race_id' => $race?->id,
            'members' => $members->count(),
            'pigeons' => $pigeonCount,
            'race_registrations' => $raceRegistrationCount,
            'mode' => $this->option('force') ? 'delete' : 'preview',
        ];

        if (! $this->option('force')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->line('预览完成；确认测试结果后，再追加 --force 执行删除。');

            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($race, $members): void {
                $race?->delete();

                if ($members->isNotEmpty()) {
                    Member::query()->whereIn('id', $members->modelKeys())->delete();
                }
            });
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $summary['mode'] = 'deleted';
        $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
