<?php

namespace App\Services;

use GeoIp2\Database\Reader;
use Throwable;

class GeoIpCountryResolver
{
    public function countryCode(?string $ip): ?string
    {
        if (! $ip || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return null;
        }

        $path = (string) config('services.geoip.country_database_path', '');
        if ($path === '' || ! is_readable($path)) {
            return null;
        }

        try {
            $reader = new Reader($path);

            return strtoupper((string) ($reader->country($ip)->country->isoCode ?? '')) ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}
