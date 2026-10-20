<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Support;

/**
 * Constant-time comparison of a stored hash with the hash of a presented token. An interface only
 * so tests can observe that both the hit and the miss path compare exactly once.
 *
 * @internal
 */
interface TokenComparer
{
    public function equals(string $storedHash, string $presentedHash): bool;
}
