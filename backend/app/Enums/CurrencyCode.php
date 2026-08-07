<?php

namespace App\Enums;

enum CurrencyCode: string
{
    case CNY = 'CNY';
    case TWD = 'TWD';

    public static function fromValue(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return self::tryFrom(strtoupper(trim((string) $value)) ?: self::CNY->value) ?? self::CNY;
    }

    public function symbol(): string
    {
        return match ($this) {
            self::CNY => '¥',
            self::TWD => 'NT$',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::CNY => __('人民币（CNY）'),
            self::TWD => __('新台币（TWD）'),
        };
    }
}
