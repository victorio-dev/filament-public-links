<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Support;

/**
 * The access log stores an HMAC of the client IP, never the address itself (rules E4, E5). The
 * key is access_log.ip_hash_key, or one derived from the app key when that is not set.
 *
 * @internal
 */
final class IpHasher
{
    public static function hash(?string $ip): string
    {
        return hash_hmac('sha256', (string) $ip, self::key());
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
