<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Exceptions;

final class InsecureTokenConfiguration extends PublicLinkException
{
    public static function tooFewBytes(int $configured, int $minimum): self
    {
        return new self(sprintf(
            'filament-public-links.tokens.bytes is %d; public tokens need at least %d random bytes.',
            $configured,
            $minimum,
        ));
    }
}
