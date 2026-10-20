<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Exceptions;

final class UnknownPurpose extends PublicLinkException
{
    public static function for(string $morphClass, string $purpose): self
    {
        return new self(sprintf(
            'No public link handler is registered for %s and purpose "%s". Register one with '
            .'PublicLinks::handle() in a service provider.',
            $morphClass,
            $purpose,
        ));
    }
}
