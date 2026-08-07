<?php

// [IN]: Request cookies, client IPs, and mocked MaxMind country results / 请求 Cookie、客户端 IP 与模拟 MaxMind 国家结果
// [OUT]: Manual-cookie, IP, fallback, private-IP, IPv6, and Accept-Language precedence assertions / 手动 Cookie、IP、兜底、内网 IP、IPv6 与 Accept-Language 优先级断言
// [POS]: Locale resolver unit test / 语言解析器单元测试
// Protocol: When updating me, sync this header + parent folder's .folder.md
// 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

namespace Tests\Unit;

use App\Services\GeoIpCountryResolver;
use App\Services\LocaleResolver;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\TestCase;

class LocaleResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_manual_cookie_has_priority_over_ip_and_accept_language(): void
    {
        $geoIp = Mockery::mock(GeoIpCountryResolver::class);
        $geoIp->shouldReceive('countryCode')->never();
        $resolver = new LocaleResolver($geoIp);

        $request = Request::create(
            '/',
            'GET',
            [],
            ['app_locale' => 'zh-TW'],
            [],
            ['REMOTE_ADDR' => '1.1.1.1', 'HTTP_ACCEPT_LANGUAGE' => 'zh-CN'],
        );

        $this->assertSame(['locale' => 'zh_TW', 'source' => 'manual'], $resolver->resolve($request));
    }

    public function test_mainland_china_ip_resolves_to_simplified_chinese(): void
    {
        $geoIp = Mockery::mock(GeoIpCountryResolver::class);
        $geoIp->shouldReceive('countryCode')->with('1.1.1.1')->once()->andReturn('CN');
        $resolver = new LocaleResolver($geoIp);

        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '1.1.1.1']);

        $this->assertSame(['locale' => 'zh_CN', 'source' => 'ip'], $resolver->resolve($request));
    }

    public function test_non_mainland_ip_resolves_to_taiwan_traditional_chinese(): void
    {
        $geoIp = Mockery::mock(GeoIpCountryResolver::class);
        $geoIp->shouldReceive('countryCode')->with('2001:4860:4860::8888')->once()->andReturn('US');
        $resolver = new LocaleResolver($geoIp);

        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '2001:4860:4860::8888']);

        $this->assertSame(['locale' => 'zh_TW', 'source' => 'ip'], $resolver->resolve($request));
    }

    public function test_private_or_unresolvable_ip_falls_back_to_simplified_chinese(): void
    {
        $geoIp = Mockery::mock(GeoIpCountryResolver::class);
        $geoIp->shouldReceive('countryCode')->never();
        $resolver = new LocaleResolver($geoIp);

        $private = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '192.168.1.10']);
        $missing = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '1.1.1.1']);
        $geoIp->shouldReceive('countryCode')->with('1.1.1.1')->once()->andReturn(null);

        $this->assertSame(['locale' => 'zh_CN', 'source' => 'fallback'], $resolver->resolve($private));
        $this->assertSame(['locale' => 'zh_CN', 'source' => 'fallback'], $resolver->resolve($missing));
    }

    public function test_accept_language_does_not_override_ip_result(): void
    {
        $geoIp = Mockery::mock(GeoIpCountryResolver::class);
        $geoIp->shouldReceive('countryCode')->with('8.8.8.8')->once()->andReturn('US');
        $resolver = new LocaleResolver($geoIp);

        $request = Request::create(
            '/',
            'GET',
            [],
            [],
            [],
            ['REMOTE_ADDR' => '8.8.8.8', 'HTTP_ACCEPT_LANGUAGE' => 'zh-CN,zh;q=0.9'],
        );

        $this->assertSame(['locale' => 'zh_TW', 'source' => 'ip'], $resolver->resolve($request));
    }

    public function test_malformed_forwarded_ip_falls_back_without_querying_geoip(): void
    {
        $geoIp = Mockery::mock(GeoIpCountryResolver::class);
        $geoIp->shouldReceive('countryCode')->never();
        $resolver = new LocaleResolver($geoIp);

        $request = Request::create(
            '/',
            'GET',
            [],
            [],
            [],
            [
                'REMOTE_ADDR' => '8.8.8.8',
                'HTTP_X_FORWARDED_FOR' => 'not-an-ip',
            ],
        );

        $this->assertSame(['locale' => 'zh_CN', 'source' => 'fallback'], $resolver->resolve($request));
    }
}
