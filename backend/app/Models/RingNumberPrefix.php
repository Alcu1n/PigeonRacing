<?php

// [IN]: Reusable ring prefix and suffix width / 可复用足环前缀与尾号位数
// [OUT]: Quick-entry ring-number configuration / 快速录入足环号码配置
// [POS]: Ring-sale prefix model / 售环前缀模型
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class RingNumberPrefix extends Model
{
    protected $fillable = ['prefix', 'suffix_width', 'is_enabled'];

    protected function casts(): array
    {
        return ['suffix_width' => 'integer', 'is_enabled' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (RingNumberPrefix $prefix): void {
            if ((int) $prefix->suffix_width < 1 || (int) $prefix->suffix_width > 12) {
                throw ValidationException::withMessages([
                    'suffix_width' => '尾号位数必须在 1 到 12 位之间。',
                ]);
            }
        });

        static::updating(function (RingNumberPrefix $prefix): void {
            if ($prefix->isDirty(['prefix', 'suffix_width']) && $prefix->isUsed()) {
                throw ValidationException::withMessages([
                    'prefix' => '已用于售环记录的号码前缀不能修改文本或尾号位数，只能停用。',
                ]);
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(RingSaleItem::class);
    }

    public function isUsed(): bool
    {
        return $this->items()->exists();
    }
}
