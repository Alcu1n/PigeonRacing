<?php
// [IN]: Application setting rows / 应用设置行
// [OUT]: Key-value setting accessors / 键值设置访问器
// [POS]: Backend global setting model / 后端全局设置模型
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    public const BRAND_LOGO_PATH = 'brand_logo_path';

    protected $fillable = ['key', 'value'];

    public static function getValue(string $key): ?string
    {
        return static::query()->where('key', $key)->value('value');
    }

    public static function putValue(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
