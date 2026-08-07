<?php

// [IN]: MaxMind Country database path and candidate IP addresses / MaxMind Country 資料庫路徑與候選 IP 位址
// [OUT]: Private, malformed, and missing-database fallback assertions / 內網、格式錯誤與資料庫缺失回退斷言
// [POS]: GeoIP country resolver unit test / GeoIP 國家解析器單元測試
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 協議:更新本文件時，同步更新此頭注釋及所屬文件夾的 .folder.md

namespace Tests\Unit;

use App\Services\GeoIpCountryResolver;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class GeoIpCountryResolverTest extends TestCase
{
    public function test_private_and_malformed_ips_are_rejected_before_database_access(): void
    {
        Config::set('services.geoip.country_database_path', '/does/not/exist.mmdb');
        $resolver = new GeoIpCountryResolver;

        $this->assertNull($resolver->countryCode('192.168.1.10'));
        $this->assertNull($resolver->countryCode('not-an-ip'));
        $this->assertNull($resolver->countryCode('2001:db8::1'));
    }

    public function test_missing_database_falls_back_without_throwing_for_public_ipv4_and_ipv6(): void
    {
        Config::set('services.geoip.country_database_path', '/does/not/exist.mmdb');
        $resolver = new GeoIpCountryResolver;

        $this->assertNull($resolver->countryCode('8.8.8.8'));
        $this->assertNull($resolver->countryCode('2001:4860:4860::8888'));
    }
}
