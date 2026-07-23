<?php

// [IN]: Configured ring prefixes or full ring-number ranges / 已配置足环前缀或完整足环号码段
// [OUT]: Normalized inclusive ranges with stable leading zeroes / 保留前导零的标准化闭区间
// [POS]: Ring-sale number parsing unit tests / 售环号码解析单元测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Unit;

use App\Support\RingNumberRange;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RingNumberRangeTest extends TestCase
{
    public function test_it_builds_an_inclusive_range_from_a_configured_prefix(): void
    {
        $range = RingNumberRange::fromPrefix('2026-13-055', 4, '987', '0990');

        $this->assertSame('2026-13-0550987', $range->startRing);
        $this->assertSame('2026-13-0550990', $range->endRing);
        $this->assertSame(4, $range->quantity());
        $this->assertSame([
            '2026-13-0550987',
            '2026-13-0550988',
            '2026-13-0550989',
            '2026-13-0550990',
        ], iterator_to_array($range->rings()));
    }

    public function test_it_parses_the_final_numeric_run_from_full_ring_numbers(): void
    {
        $range = RingNumberRange::fromFull('2026-13-0550987', '2026-13-0550990');

        $this->assertSame('2026-13-', $range->numberPrefix);
        $this->assertSame(7, $range->suffixWidth);
        $this->assertSame(4, $range->quantity());
    }

    public function test_it_rejects_incompatible_or_reversed_ranges(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RingNumberRange::fromFull('2026-13-0001', '2027-13-0002');
    }
}
