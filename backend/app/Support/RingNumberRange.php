<?php

// [IN]: Prefix-based or full ring-number range input / 基于前缀或完整足环号码段的输入
// [OUT]: Validated inclusive ring-number sequence / 已校验的足环号码闭区间序列
// [POS]: Ring-sale number value object / 售环号码值对象
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Support;

use Generator;
use InvalidArgumentException;

final readonly class RingNumberRange
{
    private function __construct(
        public string $numberPrefix,
        public int $suffixWidth,
        public int $startNumber,
        public int $endNumber,
        public string $startRing,
        public string $endRing,
    ) {}

    public static function fromPrefix(
        string $prefix,
        int $suffixWidth,
        string|int $startSuffix,
        string|int $endSuffix,
    ): self {
        $prefix = trim($prefix);
        self::assertPrefix($prefix);

        if ($suffixWidth < 1 || $suffixWidth > 12) {
            throw new InvalidArgumentException('尾号位数必须在 1 到 12 位之间。');
        }

        $start = self::normalizeSuffix($startSuffix, $suffixWidth, '起始尾号');
        $end = self::normalizeSuffix($endSuffix, $suffixWidth, '结束尾号');

        return self::make($prefix, $suffixWidth, $start, $end);
    }

    public static function fromFull(string $startRing, string $endRing): self
    {
        [$startPrefix, $startSuffix] = self::splitFullRing($startRing, '起始足环号');
        [$endPrefix, $endSuffix] = self::splitFullRing($endRing, '结束足环号');

        if ($startPrefix !== $endPrefix || strlen($startSuffix) !== strlen($endSuffix)) {
            throw new InvalidArgumentException('完整起止足环号的前缀和尾号位数必须一致。');
        }

        return self::make(
            $startPrefix,
            strlen($startSuffix),
            (int) $startSuffix,
            (int) $endSuffix,
        );
    }

    public function quantity(): int
    {
        return $this->endNumber - $this->startNumber + 1;
    }

    /** @return Generator<int, string> */
    public function rings(): Generator
    {
        for ($number = $this->startNumber; $number <= $this->endNumber; $number++) {
            yield $this->format($number);
        }
    }

    public function canonical(string $ring): string
    {
        return mb_strtoupper(trim($ring), 'UTF-8');
    }

    private static function make(string $prefix, int $suffixWidth, int $start, int $end): self
    {
        if ($end < $start) {
            throw new InvalidArgumentException('结束号码不能小于起始号码。');
        }

        $max = (10 ** $suffixWidth) - 1;
        if ($start > $max || $end > $max) {
            throw new InvalidArgumentException("尾号不能超过 {$suffixWidth} 位。");
        }

        $startRing = $prefix.str_pad((string) $start, $suffixWidth, '0', STR_PAD_LEFT);
        $endRing = $prefix.str_pad((string) $end, $suffixWidth, '0', STR_PAD_LEFT);

        if (mb_strlen($startRing) > 128 || mb_strlen($endRing) > 128) {
            throw new InvalidArgumentException('完整足环号码不能超过 128 个字符。');
        }

        return new self($prefix, $suffixWidth, $start, $end, $startRing, $endRing);
    }

    private static function normalizeSuffix(string|int $suffix, int $width, string $label): int
    {
        $suffix = trim((string) $suffix);

        if ($suffix === '' || ! ctype_digit($suffix)) {
            throw new InvalidArgumentException("{$label}只能包含数字。");
        }

        if (strlen($suffix) > $width) {
            throw new InvalidArgumentException("{$label}不能超过 {$width} 位。");
        }

        return (int) $suffix;
    }

    /** @return array{string, string} */
    private static function splitFullRing(string $ring, string $label): array
    {
        $ring = trim($ring);

        if ($ring === '' || ! preg_match('/^(.*?)(\d+)$/u', $ring, $matches)) {
            throw new InvalidArgumentException("{$label}必须以数字结尾。");
        }

        self::assertPrefix($matches[1], allowEmpty: true);

        if (strlen($matches[2]) > 12) {
            throw new InvalidArgumentException('完整足环号末尾连续数字不能超过 12 位。');
        }

        return [$matches[1], $matches[2]];
    }

    private static function assertPrefix(string $prefix, bool $allowEmpty = false): void
    {
        if ((! $allowEmpty && $prefix === '') || preg_match('/[\x00-\x1F\x7F]/u', $prefix)) {
            throw new InvalidArgumentException('足环前缀格式无效。');
        }

        if (mb_strlen($prefix) > 116) {
            throw new InvalidArgumentException('足环前缀不能超过 116 个字符。');
        }
    }

    private function format(int $number): string
    {
        return $this->numberPrefix.str_pad((string) $number, $this->suffixWidth, '0', STR_PAD_LEFT);
    }
}
