<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Support;

use Victorio\PublicLinks\Exceptions\InsecureTokenConfiguration;

/**
 * Generates public tokens and their lookup hash (rule E1, ADR-0008).
 *
 * @internal
 */
final class TokenFactory
{
    public const int MINIMUM_BYTES = 32;

    /**
     * A URL-safe token: base64url without padding, 43 characters for 32 bytes.
     */
    public static function generate(): string
    {
        $bytes = self::bytes();

        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    /**
     * The value stored in `token_hash` and used for the lookup. SHA-256 is fixed by rule E1.
     */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Constant-time comparison of a token against a stored hash.
     */
    public static function matches(string $token, string $hash): bool
    {
        return hash_equals($hash, self::hash($token));
    }

    /**
     * The configured byte count, refusing anything below the rule E1 minimum.
     *
     * @return int<32, max>
     */
    public static function bytes(): int
    {
        $bytes = PublicLinksConfig::tokenBytes();

        if ($bytes < self::MINIMUM_BYTES) {
            throw InsecureTokenConfiguration::tooFewBytes($bytes, self::MINIMUM_BYTES);
        }

        return $bytes;
    }
}
