<?php
// [IN]: Member, race, config version, idempotency key, and normalized entries / 会员、赛事、配置版本、幂等键与标准化报名项目
// [OUT]: Validated registration with snapshot entries / 已校验报名及快照明细
// [POS]: Backend trusted registration transaction service / 后端可信报名事务服务
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Models\Member;
use App\Models\Pigeon;
use App\Models\Race;
use App\Models\RaceProject;
use App\Models\Registration;
use App\Models\RegistrationEntry;
use App\Models\RegistrationEntryPigeon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RegistrationSubmissionService
{
    public function __construct(private readonly RaceCacheService $cache) {}

    public function submit(Member $member, Race $race, int $configVersion, string $idempotencyKey, array $entries): Registration
    {
        $existingSameRequest = Registration::query()
            ->where('member_id', $member->id)
            ->where('race_id', $race->id)
            ->where('idempotency_key', $idempotencyKey)
            ->with(['entries.pigeons'])
            ->first();

        if ($existingSameRequest) {
            return $existingSameRequest;
        }

        $this->assertRaceCanAccept($race, $configVersion);

        $projects = $race->projects()
            ->where('is_enabled', true)
            ->get()
            ->keyBy('id');
        $pigeons = $member->pigeons()
            ->where('status', 'normal')
            ->whereIn('id', $this->flattenPigeonIds($entries))
            ->get()
            ->keyBy('id');
        $validated = $this->validateEntries($entries, $projects, $pigeons);

        return DB::transaction(function () use ($member, $race, $idempotencyKey, $validated): Registration {
            $registration = Registration::query()
                ->where('race_id', $race->id)
                ->where('member_id', $member->id)
                ->lockForUpdate()
                ->first();

            if ($registration && ! $race->allow_member_edit) {
                throw new RegistrationRuleException('registration_already_submitted', '该赛事已提交报名，当前赛事不允许会员自行修改。', 409);
            }

            if ($registration && $race->registration_end_at < now()) {
                throw new RegistrationRuleException('registration_closed', '报名已截止，不能修改报名。', 409);
            }

            $registration ??= new Registration([
                'race_id' => $race->id,
                'member_id' => $member->id,
                'registration_no' => $this->makeRegistrationNo($race),
            ]);

            $registration->fill([
                'total_amount_cent' => $validated['total_amount_cent'],
                'status' => $race->require_admin_confirm ? RegistrationStatus::PendingConfirmation : RegistrationStatus::Submitted,
                'idempotency_key' => $idempotencyKey,
                'submitted_at' => now(),
            ])->save();

            $registration->entries()->delete();
            $this->writeSnapshots($registration, $validated['entries']);
            $this->cache->forgetBootstrap($race, $member);

            return $registration->fresh(['entries.pigeons']);
        });
    }

    public function validateEntries(array $entries, Collection $projects, Collection $pigeons): array
    {
        if ($entries === []) {
            throw new RegistrationRuleException('empty_entries', '请至少选择一项报名项目。');
        }

        $normalized = [];
        $entryCountByProject = [];
        $pigeonUsageByProject = [];
        $totalAmount = 0;

        foreach ($entries as $index => $entry) {
            $project = $projects->get((int) ($entry['project_id'] ?? 0));
            if (! $project instanceof RaceProject) {
                throw new RegistrationRuleException('project_disabled', '报名项目不存在或已停用。');
            }

            $pigeonIds = array_values(array_unique(array_map('intval', $entry['pigeon_ids'] ?? [])));
            if (count($pigeonIds) !== $project->group_size) {
                throw new RegistrationRuleException('group_size_mismatch', "项目 {$project->name} 必须选择 {$project->group_size} 羽。");
            }

            $entryPigeons = [];
            foreach ($pigeonIds as $pigeonId) {
                $pigeon = $pigeons->get($pigeonId);
                if (! $pigeon instanceof Pigeon) {
                    throw new RegistrationRuleException('pigeon_not_owned', '存在不属于当前会员或不可报名的足环。', 403);
                }
                $entryPigeons[] = $pigeon;
                $pigeonUsageByProject[$project->id][$pigeonId] = ($pigeonUsageByProject[$project->id][$pigeonId] ?? 0) + 1;
            }

            $entryCountByProject[$project->id] = ($entryCountByProject[$project->id] ?? 0) + 1;
            $this->assertProjectLimits($project, $entryCountByProject[$project->id], $pigeonUsageByProject[$project->id]);

            $totalAmount += $project->price_cent;
            $normalized[] = [
                'project' => $project,
                'pigeons' => $entryPigeons,
                'group_index' => $entryCountByProject[$project->id],
                'source_index' => $index,
            ];
        }

        return ['entries' => $normalized, 'total_amount_cent' => $totalAmount];
    }

    private function assertRaceCanAccept(Race $race, int $configVersion): void
    {
        if (! $race->isOpenForRegistration()) {
            throw new RegistrationRuleException('race_not_open', '赛事当前不在报名时间内。', 409);
        }

        if ($race->config_version !== $configVersion) {
            throw new RegistrationRuleException('config_version_changed', '赛事报名项目已更新，请刷新页面后重新确认报名。', 409);
        }
    }

    private function assertProjectLimits(RaceProject $project, int $entryCount, array $pigeonUsage): void
    {
        if ($project->max_entries_per_member !== null && $entryCount > $project->max_entries_per_member) {
            throw new RegistrationRuleException('project_entry_limit_exceeded', "项目 {$project->name} 已超过每会员报名上限。");
        }

        foreach ($pigeonUsage as $usage) {
            if (! $project->allow_repeat_pigeon_in_project && $usage > 1) {
                throw new RegistrationRuleException('repeat_pigeon_not_allowed', "项目 {$project->name} 不允许同一足环重复进入多个组合。");
            }

            if ($project->max_usage_per_pigeon !== null && $usage > $project->max_usage_per_pigeon) {
                throw new RegistrationRuleException('pigeon_usage_limit_exceeded', "项目 {$project->name} 已超过每只足环最大使用次数。");
            }
        }
    }

    private function flattenPigeonIds(array $entries): array
    {
        return collect($entries)
            ->flatMap(fn (array $entry): array => $entry['pigeon_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function makeRegistrationNo(Race $race): string
    {
        return 'REG'.now()->format('YmdHis').str_pad((string) $race->id, 4, '0', STR_PAD_LEFT).random_int(100, 999);
    }

    private function writeSnapshots(Registration $registration, array $entries): void
    {
        foreach ($entries as $entry) {
            /** @var RaceProject $project */
            $project = $entry['project'];
            $snapshot = RegistrationEntry::query()->create([
                'registration_id' => $registration->id,
                'race_project_id' => $project->id,
                'project_name_snapshot' => $project->name,
                'group_size_snapshot' => $project->group_size,
                'price_cent_snapshot' => $project->price_cent,
                'group_index' => $entry['group_index'],
                'created_at' => now(),
            ]);

            $pigeonRows = collect($entry['pigeons'])->values()->map(fn (Pigeon $pigeon, int $order): array => [
                'registration_entry_id' => $snapshot->id,
                'pigeon_id' => $pigeon->id,
                'ring_number_snapshot' => $pigeon->ring_number,
                'sort_order' => $order + 1,
                'created_at' => now(),
            ])->all();

            RegistrationEntryPigeon::query()->insert($pigeonRows);
        }
    }
}
