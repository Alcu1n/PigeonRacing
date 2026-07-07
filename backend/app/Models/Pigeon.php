<?php
// [IN]: Imported pigeon ring rows / 已导入足环行
// [OUT]: Member-owned pigeon identity and cache invalidation events / 会员所属足环身份与缓存失效事件
// [POS]: Backend pigeon ring model / 后端足环模型
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Models;

use App\Services\RaceCacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pigeon extends Model
{
    protected $fillable = ['member_id', 'loft_number', 'participant_name', 'ring_number', 'import_batch_id', 'status'];

    protected static function booted(): void
    {
        static::saved(fn (Pigeon $pigeon) => $pigeon->forgetOwnerCaches());
        static::deleted(fn (Pigeon $pigeon) => $pigeon->forgetOwnerCaches());
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function progressiveStageEntries(): HasMany
    {
        return $this->hasMany(ProgressiveStageEntry::class);
    }

    private function forgetOwnerCaches(): void
    {
        collect([$this->member_id, $this->getOriginal('member_id')])
            ->filter()
            ->unique()
            ->each(fn (int $memberId) => app(RaceCacheService::class)->forgetMemberPigeonsById($memberId));
    }
}
