<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Http;

use Symfony\Component\HttpFoundation\Response;
use Victorio\PublicLinks\Enums\AccessOutcome;

/**
 * The plain 404/410 pages. Step 3 of the design brands them; the view and its data stay the same.
 *
 * @internal
 */
final class PublicLinkResponses
{
    public static function status(AccessOutcome $outcome, int $status): Response
    {
        $key = match ($outcome) {
            AccessOutcome::Expired => 'expired',
            AccessOutcome::Exhausted => 'exhausted',
            AccessOutcome::Revoked, AccessOutcome::MissingTarget, AccessOutcome::MissingHandler => 'revoked',
            default => 'not_found',
        };

        return response()->view('filament-public-links::status', ['messageKey' => $key], $status);
    }
}
