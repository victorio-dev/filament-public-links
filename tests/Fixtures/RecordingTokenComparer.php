<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Tests\Fixtures;

use Victorio\PublicLinks\Support\HashEqualsTokenComparer;
use Victorio\PublicLinks\Support\TokenComparer;

/**
 * Counts constant-time comparisons, to show hit and miss take the same steps.
 */
final class RecordingTokenComparer implements TokenComparer
{
    public int $calls = 0;

    public function equals(string $storedHash, string $presentedHash): bool
    {
        $this->calls++;

        return (new HashEqualsTokenComparer)->equals($storedHash, $presentedHash);
    }
}
