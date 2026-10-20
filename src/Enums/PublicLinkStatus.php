<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Enums;

enum PublicLinkStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Exhausted = 'exhausted';

    public function label(): string
    {
        $label = __('filament-public-links::filament-public-links.status.'.$this->value);

        return is_string($label) ? $label : $this->value;
    }
}
