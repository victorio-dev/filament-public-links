<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Exceptions;

final class PermanentLinksDisabled extends PublicLinkException
{
    public static function make(): self
    {
        return new self(
            'Permanent public links are disabled. Set filament-public-links.defaults.allow_permanent '
            .'to true, or give the link an expiry.',
        );
    }
}
