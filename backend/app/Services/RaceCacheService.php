<?php
// [IN]: Race, project, member, and pigeon read models / 赛事、项目、会员与足环读取模型
// [OUT]: Cached bootstrap payloads and invalidation hooks / 已缓存初始化数据与失效钩子
// [POS]: Backend read-cache coordinator / 后端读取缓存协调器
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Services;

use App\Models\Member;
use App\Models\Race;
use Illuminate\Support\Facades\Cache;

class RaceCacheService
{
    public function bootstrap(Race $race, Member $member): array
    {
        return Cache::remember(
            $this->bootstrapKey($race->id, $member->id),
            now()->addMinutes(5),
            fn (): array => $this->buildBootstrap($race->fresh(['projects']), $member->fresh())
        );
    }

    public function forgetRace(Race $race): void
    {
        Cache::forget($this->raceConfigKey($race->id));
    }

    public function forgetMemberPigeons(Member $member): void
    {
        $this->forgetMemberPigeonsById($member->id);
    }

    public function forgetMemberPigeonsById(int $memberId): void
    {
        Cache::forget($this->memberPigeonsKey($memberId));

        Race::query()
            ->pluck('id')
            ->each(fn (int $raceId) => Cache::forget($this->bootstrapKey($raceId, $memberId)));
    }

    public function forgetBootstrap(Race $race, Member $member): void
    {
        Cache::forget($this->bootstrapKey($race->id, $member->id));
    }

    private function buildBootstrap(Race $race, Member $member): array
    {
        $projects = Cache::remember(
            $this->raceConfigKey($race->id),
            now()->addMinutes(15),
            fn () => $race->projects()
                ->where('is_enabled', true)
                ->orderBy('sort_order')
                ->get(['id', 'race_id', 'name', 'group_size', 'price_cent', 'description', 'sort_order', 'is_enabled', 'allow_repeat_pigeon_in_project', 'max_entries_per_member', 'max_usage_per_pigeon'])
                ->values()
        );

        $pigeons = Cache::remember(
            $this->memberPigeonsKey($member->id),
            now()->addMinutes(10),
            fn () => $member->pigeons()
                ->where('status', 'normal')
                ->orderBy('ring_number')
                ->get(['id', 'ring_number'])
                ->values()
        );

        $existing = $member->registrations()
            ->with(['entries.pigeons'])
            ->where('race_id', $race->id)
            ->latest('submitted_at')
            ->first();

        return [
            'race' => [
                'id' => $race->id,
                'name' => $race->name,
                'description' => $race->description,
                'registration_start_at' => optional($race->registration_start_at)->toDateTimeString(),
                'registration_end_at' => optional($race->registration_end_at)->toDateTimeString(),
                'status' => $race->isOpenForRegistration() ? 'open' : $race->status->value,
                'config_version' => $race->config_version,
                'allow_member_edit' => $race->allow_member_edit,
            ],
            'member' => [
                'id' => $member->id,
                'loft_number' => $member->loft_number,
                'participant_name' => $member->participant_name,
            ],
            'projects' => $projects,
            'pigeons' => $pigeons,
            'existing_registration' => $existing ? $this->serializeRegistration($existing) : null,
        ];
    }

    public function serializeRegistration($registration): array
    {
        return [
            'id' => $registration->id,
            'registration_no' => $registration->registration_no,
            'status' => $registration->status->value,
            'total_amount_cent' => $registration->total_amount_cent,
            'submitted_at' => optional($registration->submitted_at)->toDateTimeString(),
            'entries' => $registration->entries->map(fn ($entry): array => [
                'project_id' => $entry->race_project_id,
                'project_name' => $entry->project_name_snapshot,
                'group_size' => $entry->group_size_snapshot,
                'price_cent' => $entry->price_cent_snapshot,
                'group_index' => $entry->group_index,
                'pigeons' => $entry->pigeons->map(fn ($pigeon): array => [
                    'pigeon_id' => $pigeon->pigeon_id,
                    'ring_number' => $pigeon->ring_number_snapshot,
                    'sort_order' => $pigeon->sort_order,
                ])->values(),
            ])->values(),
        ];
    }

    private function raceConfigKey(int $raceId): string
    {
        return "race:{$raceId}:config";
    }

    private function memberPigeonsKey(int $memberId): string
    {
        return "member:{$memberId}:pigeons";
    }

    private function bootstrapKey(int $raceId, int $memberId): string
    {
        return "race:{$raceId}:member:{$memberId}:bootstrap";
    }
}
