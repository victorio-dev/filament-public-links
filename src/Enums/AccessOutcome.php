<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Enums;

/**
 * The `outcome` column of an access row: what the package answered to one public request.
 */
enum AccessOutcome: string
{
    case Opened = 'opened';
    case Action = 'action';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Exhausted = 'exhausted';
    case NotFound = 'not_found';
    case UnknownAction = 'unknown_action';
    case MissingTarget = 'missing_target';
    case MissingHandler = 'missing_handler';

    public static function forStatus(PublicLinkStatus $status): self
    {
        return match ($status) {
            PublicLinkStatus::Active => self::Opened,
            PublicLinkStatus::Expired => self::Expired,
            PublicLinkStatus::Revoked => self::Revoked,
            PublicLinkStatus::Exhausted => self::Exhausted,
        };
    }
}
