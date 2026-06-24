<?php
// [IN]: Registration project snapshots / 报名项目快照
// [OUT]: Entry price, group, and pigeon links / 报名注价格、组别与足环关联
// [POS]: Backend registration entry snapshot model / 后端报名注明细快照模型
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegistrationEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'registration_id',
        'race_project_id',
        'project_name_snapshot',
        'group_size_snapshot',
        'price_cent_snapshot',
        'group_index',
        'created_at',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function pigeons(): HasMany
    {
        return $this->hasMany(RegistrationEntryPigeon::class);
    }
}
