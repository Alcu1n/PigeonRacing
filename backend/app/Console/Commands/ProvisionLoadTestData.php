<?php

// [IN]: Load-test scope options and production registration models / 压测范围选项与生产报名模型
// [OUT]: Isolated synthetic members, pigeons, race, project, and JSON credentials / 隔离的合成会员、足环、赛事、项目与 JSON 凭据
// [POS]: Manual production load-test data provision command / 手动生产压测数据准备命令
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Console\Commands;

use App\Enums\RaceStatus;
use App\Models\Member;
use App\Models\Pigeon;
use App\Models\PigeonLibrary;
use App\Models\Race;
use App\Models\RaceProject;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class ProvisionLoadTestData extends Command
{
    protected $signature = 'load-test:provision
        {--count=200 : Number of synthetic member accounts to create}
        {--submitters=30 : Number of accounts reserved for submission testing}
        {--phone-prefix=1999000 : Unique synthetic phone prefix}
        {--password= : Shared synthetic password; omitted means generate one}
        {--run-id= : Safe identifier for this retained test dataset}';

    protected $description = 'Provision isolated synthetic data for a controlled production load test.';

    public function handle(): int
    {
        try {
            $count = $this->positiveInt('count', 1, 1000);
            $submitters = $this->positiveInt('submitters', 1, $count);
            $phonePrefix = $this->phonePrefix();
            $runId = $this->runId();
            $password = $this->password();
            $marker = "[load-test:{$runId}]";
            $raceName = "[压测] {$runId}";
            $phones = $this->phones($phonePrefix, $count);

            $this->assertScopeIsUnused($marker, $raceName, $phones);

            $race = null;
            $project = null;
            $members = collect();
            $pigeons = collect();
            $now = now();
            $hashedPassword = Hash::make($password);

            DB::transaction(function () use ($marker, $raceName, $phones, $hashedPassword, $now, &$race, &$project, &$members, &$pigeons): void {
                $library = PigeonLibrary::default();

                $race = Race::query()->create([
                    'name' => $raceName,
                    'description' => "{$marker} 仅用于受控容量测试，不得用于真实报名。",
                    'registration_start_at' => $now->copy()->subMinutes(5),
                    'registration_end_at' => $now->copy()->addHours(2),
                    'status' => RaceStatus::Published,
                    'config_version' => 1,
                    'allow_member_edit' => false,
                    'require_admin_confirm' => true,
                    'is_visible' => true,
                ]);

                $project = RaceProject::query()->create([
                    'race_id' => $race->id,
                    'pigeon_library_id' => $library->id,
                    'project_type' => RaceProject::TYPE_STANDARD,
                    'name' => "{$marker} 单羽项目",
                    'group_size' => 1,
                    'price_cent' => 100,
                    'sort_order' => 1,
                    'is_enabled' => true,
                    'allow_repeat_pigeon_in_project' => false,
                    'max_entries_per_member' => 1,
                    'max_usage_per_pigeon' => 1,
                ]);

                $memberRows = collect($phones)->map(fn (string $phone, int $index): array => [
                    'phone' => $phone,
                    'password' => $hashedPassword,
                    'must_change_password' => false,
                    'loft_number' => 'LT-'.($index + 1),
                    'participant_name' => "{$marker} 会员".str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'status' => 'enabled',
                    'remark' => $marker,
                    'created_at' => $now->toDateTimeString(),
                    'updated_at' => $now->toDateTimeString(),
                ])->all();

                Member::query()->insert($memberRows);
                $members = Member::query()
                    ->where('remark', $marker)
                    ->orderBy('id')
                    ->get();

                $pigeonRows = $members->map(fn (Member $member, int $index): array => [
                    'pigeon_library_id' => $library->id,
                    'member_id' => $member->id,
                    'loft_number' => $member->loft_number,
                    'participant_name' => $member->participant_name,
                    'ring_number' => "LT-{$race->id}-".str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'status' => 'normal',
                    'created_at' => $now->toDateTimeString(),
                    'updated_at' => $now->toDateTimeString(),
                ])->all();

                Pigeon::query()->insert($pigeonRows);
                $pigeons = Pigeon::query()
                    ->whereIn('member_id', $members->pluck('id'))
                    ->orderBy('id')
                    ->get(['id', 'member_id', 'ring_number']);
            });

            $race->refresh();
            $project->refresh();

            $this->line(json_encode([
                'run_id' => $runId,
                'marker' => $marker,
                'password' => $password,
                'read_count' => $count,
                'submitter_indexes' => range(0, $submitters - 1),
                'race' => [
                    'id' => $race->id,
                    'name' => $race->name,
                    'config_version' => $race->config_version,
                    'registration_end_at' => $race->registration_end_at?->toDateTimeString(),
                ],
                'project' => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'group_size' => $project->group_size,
                ],
                'members' => $members->values()->map(function (Member $member, int $index) use ($pigeons): array {
                    $pigeon = $pigeons->firstWhere('member_id', $member->id);

                    return [
                        'index' => $index,
                        'phone' => $member->phone,
                        'pigeon_id' => $pigeon?->id,
                        'pigeon_ring_number' => $pigeon?->ring_number,
                    ];
                })->all(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function positiveInt(string $option, int $minimum, int $maximum): int
    {
        $value = filter_var($this->option($option), FILTER_VALIDATE_INT);

        if ($value === false || $value < $minimum || $value > $maximum) {
            throw new RuntimeException("--{$option} 必须是 {$minimum} 到 {$maximum} 之间的整数。");
        }

        return $value;
    }

    private function phonePrefix(): string
    {
        $prefix = trim((string) $this->option('phone-prefix'));

        if (! preg_match('/^\d{1,28}$/', $prefix)) {
            throw new RuntimeException('--phone-prefix 只能包含 1 到 28 位数字。');
        }

        return $prefix;
    }

    private function runId(): string
    {
        $runId = trim((string) ($this->option('run-id') ?: now()->format('YmdHis').'-'.bin2hex(random_bytes(3))));

        if (! preg_match('/^[A-Za-z0-9_-]{1,48}$/', $runId)) {
            throw new RuntimeException('--run-id 只能包含字母、数字、下划线和短横线，长度不超过 48。');
        }

        return $runId;
    }

    private function password(): string
    {
        $password = (string) ($this->option('password') ?: env('LOAD_TEST_PASSWORD', ''));

        if ($password === '') {
            $password = bin2hex(random_bytes(12));
        }

        if (strlen($password) < 12 || strlen($password) > 128) {
            throw new RuntimeException('压测密码长度必须是 12 到 128 个字符。');
        }

        return $password;
    }

    /**
     * @return list<string>
     */
    private function phones(string $prefix, int $count): array
    {
        $width = max(4, strlen((string) $count));

        if (strlen($prefix) + $width > 32) {
            throw new RuntimeException('--phone-prefix 与账号数量组合后超过手机号字段长度 32。');
        }

        return collect(range(1, $count))
            ->map(fn (int $index): string => $prefix.str_pad((string) $index, $width, '0', STR_PAD_LEFT))
            ->all();
    }

    /**
     * @param  list<string>  $phones
     */
    private function assertScopeIsUnused(string $marker, string $raceName, array $phones): void
    {
        if (Race::query()->where('name', $raceName)->exists()) {
            throw new RuntimeException("压测赛事 {$raceName} 已存在，请更换 --run-id。");
        }

        if (Member::query()->where('remark', $marker)->exists()) {
            throw new RuntimeException("压测标识 {$marker} 已存在，请更换 --run-id。");
        }

        if (Member::query()->whereIn('phone', $phones)->exists()) {
            throw new RuntimeException('压测手机号前缀与现有会员冲突，请更换 --phone-prefix。');
        }
    }
}
