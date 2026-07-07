<?php
// [IN]: Race project configuration rows and cache service / 赛事项目配置行与缓存服务
// [OUT]: Project rule data with race config invalidation / 带赛事配置失效的项目规则数据
// [POS]: Backend configurable registration project model / 后端可配置报名项目模型
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Models;

use App\Services\RaceCacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RaceProject extends Model
{
    public const TYPE_STANDARD = 'standard';
    public const TYPE_PROGRESSIVE_STAGE = 'progressive_stage';

    protected $fillable = [
        'race_id',
        'project_type',
        'registration_category_id',
        'stage_order',
        'name',
        'group_size',
        'price_cent',
        'description',
        'sort_order',
        'is_enabled',
        'allow_repeat_pigeon_in_project',
        'max_entries_per_member',
        'max_usage_per_pigeon',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'allow_repeat_pigeon_in_project' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (RaceProject $project): void {
            $project->project_type ??= self::TYPE_STANDARD;

            if ($project->project_type === self::TYPE_PROGRESSIVE_STAGE) {
                $project->group_size = 1;
                if ($project->registration_category_id) {
                    $categoryRaceId = RegistrationCategory::query()->whereKey($project->registration_category_id)->value('race_id');
                    if ($categoryRaceId) {
                        $project->race_id = (int) $categoryRaceId;
                    }
                }
            } else {
                $project->registration_category_id = null;
                $project->stage_order = null;
            }
        });

        static::saved(fn (RaceProject $project) => $project->refreshRaceConfig());
        static::deleted(fn (RaceProject $project) => $project->refreshRaceConfig());
    }

    public function race(): BelongsTo
    {
        return $this->belongsTo(Race::class);
    }

    public function registrationCategory(): BelongsTo
    {
        return $this->belongsTo(RegistrationCategory::class);
    }

    public function isSingle(): bool
    {
        return $this->group_size === 1;
    }

    public function isProgressiveStage(): bool
    {
        return $this->project_type === self::TYPE_PROGRESSIVE_STAGE;
    }

    private function refreshRaceConfig(): void
    {
        collect([$this->race_id, $this->getOriginal('race_id')])
            ->filter()
            ->unique()
            ->each(function (int $raceId): void {
                Race::query()->whereKey($raceId)->increment('config_version');
                app(RaceCacheService::class)->forgetRaceById($raceId);
            });
    }
}
