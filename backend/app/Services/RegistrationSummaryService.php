<?php
// [IN]: Registration records and confirmation status enum, optionally scoped to one race / 报名记录与确认状态枚举，可选按单一赛事限定范围
// [OUT]: Registration amount and loft aggregate totals / 报名金额与棚数聚合汇总
// [POS]: Backend registration summary query service / 后端报名汇总查询服务
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace App\Services;

use App\Enums\CurrencyCode;
use App\Enums\RegistrationStatus;
use App\Models\Registration;
use App\Support\CurrencyFormatter;
use Illuminate\Database\Eloquent\Builder;

class RegistrationSummaryService
{
    public function totals(?int $raceId = null): array
    {
        $money = Registration::query()
            ->when(
                $raceId !== null,
                fn (Builder $query): Builder => $query->where('race_id', $raceId),
            )
            ->select('currency_code')
            ->selectRaw('COALESCE(SUM(total_amount_cent), 0) as total_amount_cent')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status = ? THEN total_amount_cent ELSE 0 END), 0) as confirmed_amount_cent',
                [RegistrationStatus::Confirmed->value],
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status != ? THEN total_amount_cent ELSE 0 END), 0) as unconfirmed_amount_cent',
                [RegistrationStatus::Confirmed->value],
            )
            ->groupBy('currency_code')
            ->get();

        $amountsByCurrency = $money
            ->mapWithKeys(function ($row): array {
                $currency = CurrencyCode::fromValue($row->currency_code);

                return [$currency->value => [
                    'currency_code' => $currency->value,
                    'total_amount_cent' => (int) $row->total_amount_cent,
                    'confirmed_amount_cent' => (int) $row->confirmed_amount_cent,
                    'unconfirmed_amount_cent' => (int) $row->unconfirmed_amount_cent,
                    'total' => CurrencyFormatter::format((int) $row->total_amount_cent, $currency),
                    'confirmed' => CurrencyFormatter::format((int) $row->confirmed_amount_cent, $currency),
                    'unconfirmed' => CurrencyFormatter::format((int) $row->unconfirmed_amount_cent, $currency),
                ]];
            })
            ->all();

        $legacyMoney = collect($amountsByCurrency)->first() ?: [
            'total_amount_cent' => 0,
            'confirmed_amount_cent' => 0,
            'unconfirmed_amount_cent' => 0,
        ];

        return [
            'total_amount_cent' => (int) $legacyMoney['total_amount_cent'],
            'confirmed_amount_cent' => (int) $legacyMoney['confirmed_amount_cent'],
            'unconfirmed_amount_cent' => (int) $legacyMoney['unconfirmed_amount_cent'],
            'amounts_by_currency' => $amountsByCurrency,
            'loft_count' => Registration::query()
                ->when(
                    $raceId !== null,
                    fn (Builder $query): Builder => $query->where('race_id', $raceId),
                )
                ->whereNotNull('member_id')
                ->distinct('member_id')
                ->count('member_id'),
        ];
    }

    public static function formatYuan(int $cent): string
    {
        return rtrim(rtrim(number_format($cent / 100, 2, '.', ''), '0'), '.');
    }

    public static function formatAmount(int $cent, CurrencyCode|string|null $currency = null): string
    {
        return CurrencyFormatter::format($cent, $currency);
    }
}
