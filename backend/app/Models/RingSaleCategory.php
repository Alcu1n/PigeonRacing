<?php

// [IN]: Configured ring category name and unit price / 已配置足环类别名称与单价
// [OUT]: Reusable immutable-after-use ring-sale pricing option / 使用后不可改价的售环类别
// [POS]: Ring-sale category model / 售环类别模型
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class RingSaleCategory extends Model
{
    protected $fillable = ['name', 'unit_price_cent', 'is_enabled'];

    protected function casts(): array
    {
        return ['unit_price_cent' => 'integer', 'is_enabled' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (RingSaleCategory $category): void {
            if ((int) $category->unit_price_cent <= 0) {
                throw ValidationException::withMessages([
                    'unit_price_cent' => '足环类别单价必须大于 0。',
                ]);
            }
        });

        static::updating(function (RingSaleCategory $category): void {
            if ($category->isDirty(['name', 'unit_price_cent']) && $category->isUsed()) {
                throw ValidationException::withMessages([
                    'name' => '已用于售环记录的类别不能修改名称或单价，只能停用。',
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
