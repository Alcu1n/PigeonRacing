<?php
// [IN]: Race configuration rows / 赛事配置行
// [OUT]: Race lifecycle, projects, registration detail publication, and registrations / 赛事生命周期、项目、报名明细发布与报名记录
// [POS]: Backend race aggregate root / 后端赛事聚合根
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Models;

use App\Enums\RaceStatus;
use App\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Race extends Model
{
    public const DETAILS_SCOPE_CONFIRMED_ONLY = 'confirmed_only';
    public const DETAILS_SCOPE_ALL_SUBMITTED = 'all_submitted';

    protected $fillable = [
        'name',
        'description',
        'registration_start_at',
        'registration_end_at',
        'status',
        'config_version',
        'allow_member_edit',
        'require_admin_confirm',
        'is_visible',
        'registration_details_published_at',
        'registration_details_scope',
        'currency_code',
    ];

    protected function casts(): array
    {
        return [
            'registration_start_at' => 'datetime',
            'registration_end_at' => 'datetime',
            'status' => RaceStatus::class,
            'allow_member_edit' => 'boolean',
            'require_admin_confirm' => 'boolean',
            'is_visible' => 'boolean',
            'registration_details_published_at' => 'datetime',
            'currency_code' => CurrencyCode::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Race $race): void {
            $race->currency_code ??= AppSetting::defaultRegistrationCurrency();
        });

        static::updating(function (Race $race): void {
            if ($race->isDirty('currency_code')) {
                $race->currency_code = $race->getRawOriginal('currency_code') ?: CurrencyCode::CNY->value;
            }
        });
    }

    public function projects(): HasMany
    {
        return $this->hasMany(RaceProject::class);
    }

    public function registrationCategories(): HasMany
    {
        return $this->hasMany(RegistrationCategory::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function isOpenForRegistration(): bool
    {
        $now = now();

        return $this->status === RaceStatus::Published
            && $this->is_visible
            && $this->registration_start_at <= $now
            && $this->registration_end_at >= $now;
    }

    public function registrationState(): string
    {
        if ($this->isOpenForRegistration()) {
            return 'open';
        }

        if (
            $this->status === RaceStatus::Published
            && $this->is_visible
            && $this->registration_start_at > now()
        ) {
            return 'pending';
        }

        return 'ended';
    }

    public function hasPublishedRegistrationDetails(): bool
    {
        return $this->registration_details_published_at !== null;
    }
}
