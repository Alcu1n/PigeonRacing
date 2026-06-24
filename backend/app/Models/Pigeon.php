<?php
// [IN]: Imported pigeon ring rows / 已导入足环行
// [OUT]: Member-owned pigeon identity / 会员所属足环身份
// [POS]: Backend pigeon ring model / 后端足环模型
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pigeon extends Model
{
    protected $fillable = ['member_id', 'loft_number', 'participant_name', 'ring_number', 'import_batch_id', 'status'];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
