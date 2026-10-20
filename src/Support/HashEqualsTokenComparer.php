<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Support;

/**
 * @internal
 */
final class HashEqualsTokenComparer implements TokenComparer
{
    public function equals(string $storedHash, string $presentedHash): bool
    {
        return hash_equals($storedHash, $presentedHash);
    }
}
