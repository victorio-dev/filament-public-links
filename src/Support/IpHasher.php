<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Support;

use Illuminate\Http\Request;

/**
 * The HMAC of a client IP that the access log stores instead of the address (rules E4, E5).
 * Public, so other packages store the same value for the same IP and their records can be matched
 * with the access log (e.g. a decision recorded by victorio/filament-acceptance):
 *
 *     IpHasher::forRequest($request);   // = the ip_hash of this request's access row
 *
 * The contract, stable across releases (changing it is a breaking change): lowercase hex of
 * HMAC-SHA256 over the IP as Laravel reports it (`$request->ip()`, empty string when unknown),
 * keyed with `access_log.ip_hash_key`, or — when that is not set — with
 * `hash_hmac('sha256', 'filament-public-links:ip-hash', config('app.key'))`.
 */
final class IpHasher
{
    public static function hash(?string $ip): string
    {
        return hash_hmac('sha256', (string) $ip, self::key());
    }

    public static function forRequest(Request $request): string
    {
        return self::hash($request->ip());
    }

    private static function key(): string
    {
        $configured = config(PublicLinksConfig::KEY.'.access_log.ip_hash_key');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $appKey = config('app.key');

        return hash_hmac('sha256', 'filament-public-links:ip-hash', is_string($appKey) ? $appKey : '');
    }
}
