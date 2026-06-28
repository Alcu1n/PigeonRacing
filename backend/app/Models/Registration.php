<?php
// [IN]: Submitted member registration rows / 已提交会员报名行
// [OUT]: Registration total, localized status labels, colors, and snapshot entries / 报名总额、本地化状态标签、颜色与快照明细
// [POS]: Backend registration aggregate root / 后端报名聚合根
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Models;

use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Registration extends Model
{
    protected $fillable = [
        'registration_no',
        'race_id',
        'member_id',
        'total_amount_cent',
        'status',
        'idempotency_key',
        'submitted_at',
        'confirmed_at',
        'confirmed_by',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'submitted_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function race(): BelongsTo
    {
        return $this->belongsTo(Race::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(RegistrationEntry::class);
    }

    public static function statusLabel(RegistrationStatus|string|null $status): string
    {
        return self::normalizeStatus($status) === RegistrationStatus::Confirmed ? '已确认' : '未确认';
    }

    public static function statusColor(RegistrationStatus|string|null $status): string
    {
        return self::normalizeStatus($status) === RegistrationStatus::Confirmed ? 'success' : 'warning';
    }

    private static function normalizeStatus(RegistrationStatus|string|null $status): ?RegistrationStatus
    {
        return $status instanceof RegistrationStatus ? $status : RegistrationStatus::tryFrom((string) $status);
    }
}
