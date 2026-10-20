<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Exceptions;

use DateTimeInterface;

final class ExpiryTooFar extends PublicLinkException
{
    public static function beyond(DateTimeInterface $expiresAt, int $maxTtlDays): self
    {
        return new self(sprintf(
            'A public link may expire at most %d days from now; %s is later '
            .'(filament-public-links.defaults.max_ttl_days).',
            $maxTtlDays,
            $expiresAt->format(DateTimeInterface::ATOM),
        ));
    }
}
