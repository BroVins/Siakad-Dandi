<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;

class TrustConfiguredProxies extends TrustProxies
{
    protected function proxies()
    {
        // Never trust arbitrary internet clients or a wildcard from a copied configuration.
        return array_values(array_filter(config('security.trusted_proxies', []), static function ($proxy): bool {
            if (!is_string($proxy)) return false;
            $parts = explode('/', $proxy, 2);
            if (!filter_var($parts[0], FILTER_VALIDATE_IP)) return false;
            if (!isset($parts[1])) return true;
            return ctype_digit($parts[1]) && (int) $parts[1] <= (str_contains($parts[0], ':') ? 128 : 32);
        }));
    }
}
