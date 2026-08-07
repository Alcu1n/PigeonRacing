<?php

namespace App\Support;

use App\Enums\CurrencyCode;

class CurrencyFormatter
{
    public static function normalize(CurrencyCode|string|null $currency): CurrencyCode
    {
        return $currency instanceof CurrencyCode ? $currency : CurrencyCode::fromValue($currency);
    }

    public static function format(int $cent, CurrencyCode|string|null $currency = null): string
    {
        $code = self::normalize($currency);
        $amount = rtrim(rtrim(number_format($cent / 100, 2, '.', ''), '0'), '.');

        return $code->symbol().$amount;
    }

    public static function excelFormat(CurrencyCode|string|null $currency = null): string
    {
        return match (self::normalize($currency)) {
            CurrencyCode::CNY => '[$¥-zh-CN]#,##0.00',
            CurrencyCode::TWD => '[NT$-zh-TW]#,##0.00',
        };
    }
}
