<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Exceptions;

final class DuplicateHandler extends PublicLinkException
{
    public static function for(string $morphClass, string $purpose, string $existing): self
    {
        return new self(sprintf(
            'A public link handler for %s and purpose "%s" is already registered (%s).',
            $morphClass,
            $purpose,
            $existing,
        ));
    }
}
