<?php

namespace App\Services;

use Illuminate\Http\Request;

class LocaleResolver
{
    public const COOKIE = 'app_locale';

    public const ZH_CN = 'zh_CN';

    public const ZH_TW = 'zh_TW';

    public function __construct(private readonly GeoIpCountryResolver $geoIp) {}

    /** @return array{locale: string, source: 'manual'|'ip'|'fallback'} */
    public function resolve(Request $request): array
    {
        $manual = $request->cookie(self::COOKIE);
        if (in_array($manual, [self::ZH_CN, self::ZH_TW, 'zh-CN', 'zh-TW'], true)) {
            return [
                'locale' => str_replace('-', '_', (string) $manual),
                'source' => 'manual',
            ];
        }

        if ($this->hasProxyAnomaly($request)) {
            return [
                'locale' => self::ZH_CN,
                'source' => 'fallback',
            ];
        }

        $ip = $request->getClientIp();
        if ($ip !== null && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
            $country = $this->geoIp->countryCode($ip);

            if ($country !== null) {
                return [
                    'locale' => $country === 'CN' ? self::ZH_CN : self::ZH_TW,
                    'source' => 'ip',
                ];
            }
        }

        return [
            'locale' => self::ZH_CN,
            'source' => 'fallback',
        ];
    }

    private function hasProxyAnomaly(Request $request): bool
    {
        foreach (['X-Forwarded-For', 'X-Real-IP'] as $header) {
            $value = $request->headers->get($header);
            if ($value === null || trim($value) === '') {
                continue;
            }

            $candidates = $header === 'X-Forwarded-For' ? explode(',', $value) : [$value];
            foreach ($candidates as $candidate) {
                if (filter_var(trim($candidate), FILTER_VALIDATE_IP) === false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function frontendLocale(string $locale): string
    {
        return str_replace('_', '-', $locale);
    }
}
