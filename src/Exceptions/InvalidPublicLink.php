<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Exceptions;

use DateTimeInterface;

final class InvalidPublicLink extends PublicLinkException
{
    public static function purpose(string $purpose): self
    {
        return new self(sprintf(
            'Invalid public link purpose "%s": use 1-64 characters of a-z, 0-9, ".", "_" or "-", '
            .'starting with a letter or digit.',
            $purpose,
        ));
    }

    public static function expiryInThePast(DateTimeInterface $expiresAt): self
    {
        return new self(sprintf(
            'A public link cannot expire in the past (%s).',
            $expiresAt->format(DateTimeInterface::ATOM),
        ));
    }

    public static function usageLimit(int $maxUses): self
    {
        return new self(sprintf('A public link usage limit must be at least 1, %d given.', $maxUses));
    }

    public static function unsavedModel(): self
    {
        return new self('A public link can only be issued for a model that has been saved.');
    }
}
