<?php
// [IN]: Race-owned progressive category rows / 赛事所属递进类别行
// [OUT]: Category stages, current stage, entries, and race cache invalidation / 类别阶段、当前阶段、结果与赛事缓存失效
// [POS]: Backend progressive registration category model / 后端递进报名类别模型
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Models;

use App\Services\RaceCacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegistrationCategory extends Model
{
    public const TYPE_PROGRESSIVE = 'progressive';

    protected $fillable = [
        'race_id',
        'name',
        'type',
        'sort_order',
        'is_enabled',
        'current_stage_project_id',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (RegistrationCategory $category): void {
            $category->type ??= self::TYPE_PROGRESSIVE;
        });

        static::saved(fn (RegistrationCategory $category) => $category->refreshRaceConfig());
        static::deleted(fn (RegistrationCategory $category) => $category->refreshRaceConfig());
    }

    public function race(): BelongsTo
    {
        return $this->belongsTo(Race::class);
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(RaceProject::class, 'current_stage_project_id');
    }

    public function stageProjects(): HasMany
    {
        return $this->hasMany(RaceProject::class, 'registration_category_id')
            ->where('project_type', RaceProject::TYPE_PROGRESSIVE_STAGE)
            ->orderBy('stage_order')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function progressiveStageEntries(): HasMany
    {
        return $this->hasMany(ProgressiveStageEntry::class, 'registration_category_id');
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
