<?php

// [IN]: Pigeon library rows and race cache service / 足环库记录与赛事缓存服务
// [OUT]: Named global pigeon library model with cache invalidation / 带缓存失效的全局命名足环库模型
// [POS]: Backend pigeon library model / 后端足环库模型
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Models;

use App\Services\RaceCacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PigeonLibrary extends Model
{
    public const DEFAULT_NAME = '默认足环库';

    protected $fillable = ['name', 'is_enabled', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => app(RaceCacheService::class)->forgetAllRaceCaches());
        static::deleted(fn () => app(RaceCacheService::class)->forgetAllRaceCaches());
    }

    public static function default(): self
    {
        return self::query()->firstOrCreate(
            ['name' => self::DEFAULT_NAME],
            ['is_enabled' => true, 'sort_order' => 0],
        );
    }

    public function pigeons(): HasMany
    {
        return $this->hasMany(Pigeon::class);
    }

    public function raceProjects(): HasMany
    {
        return $this->hasMany(RaceProject::class);
    }
}
