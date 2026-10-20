<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Http;

use Symfony\Component\HttpFoundation\Response;
use Victorio\PublicLinks\Enums\AccessOutcome;
use Victorio\PublicLinks\Models\PublicLink;
use Victorio\PublicLinks\Support\PublicLinksConfig;

/**
 * The branded 404/410 pages.
 *
 * @internal
 */
final class PublicLinkResponses
{
    /**
     * $link is null on 404 (no link was found), set on 410, so a per-tenant resolver can brand it.
     */
    public static function status(AccessOutcome $outcome, int $status, ?PublicLink $link = null): Response
    {
        $key = match ($outcome) {
            AccessOutcome::Expired => 'expired',
            AccessOutcome::Exhausted => 'exhausted',
            AccessOutcome::Revoked, AccessOutcome::MissingTarget, AccessOutcome::MissingHandler => 'revoked',
            default => 'not_found',
        };

        return response()->view('filament-public-links::status', [
            'messageKey' => $key,
            'status' => $status,
            'link' => $link,
            'branding' => app(PublicLinksConfig::brandingResolver())->resolve($link),
        ], $status);
    }
}
