<?php

// [IN]: Integer-cent amounts and CNY/TWD currency codes / 整数分金额与 CNY/TWD 币种
// [OUT]: Symbol formatting and Excel number-format assertions without conversion / 不换算的符号格式化与 Excel 数字格式断言
// [POS]: Currency formatter unit test / 币种格式化器单元测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Unit;

use App\Enums\CurrencyCode;
use App\Support\CurrencyFormatter;
use PHPUnit\Framework\TestCase;

class CurrencyFormatterTest extends TestCase
{
    public function test_it_formats_cny_and_twd_without_exchange_rate_conversion(): void
    {
        $this->assertSame('¥250', CurrencyFormatter::format(25000, CurrencyCode::CNY));
        $this->assertSame('NT$250', CurrencyFormatter::format(25000, CurrencyCode::TWD));
        $this->assertSame('NT$0.5', CurrencyFormatter::format(50, CurrencyCode::TWD));
    }

    public function test_it_returns_currency_specific_excel_formats(): void
    {
        $this->assertSame('[$¥-zh-CN]#,##0.00', CurrencyFormatter::excelFormat(CurrencyCode::CNY));
        $this->assertSame('[NT$-zh-TW]#,##0.00', CurrencyFormatter::excelFormat(CurrencyCode::TWD));
    }
}
