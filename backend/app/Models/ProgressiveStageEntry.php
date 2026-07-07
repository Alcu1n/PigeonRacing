<?php
// [IN]: Member stage registration result rows / 会员阶段报名结果行
// [OUT]: Progressive stage status, snapshots, and relationships / 递进阶段状态、快照与关联关系
// [POS]: Backend progressive stage result model / 后端递进阶段报名结果模型
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Models;

use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgressiveStageEntry extends Model
{
    public const SOURCE_IMPORT = 'import';
    public const SOURCE_MEMBER = 'member';

    protected $fillable = [
        'registration_id',
        'race_id',
        'registration_category_id',
        'race_project_id',
        'member_id',
        'pigeon_id',
        'loft_number_snapshot',
        'participant_name_snapshot',
        'ring_number_snapshot',
        'project_name_snapshot',
        'price_cent_snapshot',
        'status',
        'source',
        'submitted_at',
        'confirmed_at',
        'confirmed_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'submitted_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function race(): BelongsTo
    {
        return $this->belongsTo(Race::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RegistrationCategory::class, 'registration_category_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(RaceProject::class, 'race_project_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function pigeon(): BelongsTo
    {
        return $this->belongsTo(Pigeon::class);
    }
}
