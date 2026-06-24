<?php
// [IN]: Race project configuration rows / 赛事项目配置行
// [OUT]: Project rule and pricing data / 项目规则与价格数据
// [POS]: Backend configurable registration project model / 后端可配置报名项目模型
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RaceProject extends Model
{
    protected $fillable = [
        'race_id',
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

    public function race(): BelongsTo
    {
        return $this->belongsTo(Race::class);
    }

    public function isSingle(): bool
    {
        return $this->group_size === 1;
    }
}
