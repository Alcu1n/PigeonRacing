<?php
// [IN]: Registration entry to pigeon snapshot rows / 报名项目与足环快照关联行
// [OUT]: Ordered ring-number snapshot membership / 有序足环号码快照成员
// [POS]: Backend registration pigeon snapshot model / 后端报名足环快照模型
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationEntryPigeon extends Model
{
    public $timestamps = false;

    protected $fillable = ['registration_entry_id', 'pigeon_id', 'ring_number_snapshot', 'sort_order', 'created_at'];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(RegistrationEntry::class, 'registration_entry_id');
    }
}
