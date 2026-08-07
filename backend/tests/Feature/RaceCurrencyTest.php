<?php

// [IN]: AppSetting default currency and Race currency snapshot / AppSetting 默认币种与赛事币种快照
// [OUT]: New-race inheritance, immutable race currency, and registration snapshot assertions / 新赛事继承、赛事币种不可变与报名快照断言
// [POS]: Race currency feature test / 赛事币种功能测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Feature;

use App\Enums\CurrencyCode;
use App\Enums\RaceStatus;
use App\Models\AppSetting;
use App\Models\Member;
use App\Models\Race;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RaceCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_races_snapshot_the_current_default_currency_but_existing_races_do_not_change(): void
    {
        $cnyRace = Race::query()->create([
            'name' => '人民币赛事',
            'registration_start_at' => now()->subHour(),
            'registration_end_at' => now()->addHour(),
            'status' => RaceStatus::Published,
            'is_visible' => true,
        ]);

        AppSetting::putValue(AppSetting::REGISTRATION_DEFAULT_CURRENCY, CurrencyCode::TWD->value);
        $twdRace = Race::query()->create([
            'name' => '新台币赛事',
            'registration_start_at' => now()->subHour(),
            'registration_end_at' => now()->addHour(),
            'status' => RaceStatus::Published,
            'is_visible' => true,
        ]);

        $cnyRace->update(['currency_code' => CurrencyCode::TWD->value]);

        $this->assertSame(CurrencyCode::CNY, $cnyRace->fresh()->currency_code);
        $this->assertSame(CurrencyCode::TWD, $twdRace->fresh()->currency_code);
    }

    public function test_registration_currency_is_a_persisted_snapshot(): void
    {
        $member = Member::query()->create([
            'phone' => '13800000001',
            'password' => 'password',
            'must_change_password' => false,
            'loft_number' => 'TWD-LOFT',
            'participant_name' => 'TWD会员',
            'status' => 'active',
        ]);

        $race = Race::query()->create([
            'name' => '新台币赛事',
            'currency_code' => CurrencyCode::TWD,
            'registration_start_at' => now()->subHour(),
            'registration_end_at' => now()->addHour(),
            'status' => RaceStatus::Published,
            'is_visible' => true,
        ]);

        $registration = Registration::query()->create([
            'registration_no' => 'TWD-001',
            'race_id' => $race->id,
            'member_id' => $member->id,
            'total_amount_cent' => 25000,
            'currency_code' => CurrencyCode::TWD,
            'status' => 'submitted',
            'idempotency_key' => 'twd-registration-001',
            'submitted_at' => now(),
        ]);

        $race->update(['currency_code' => CurrencyCode::CNY->value]);

        $this->assertSame(CurrencyCode::TWD, $registration->fresh()->currency_code);
    }
}
