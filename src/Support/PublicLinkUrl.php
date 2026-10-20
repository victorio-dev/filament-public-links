<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Support;

/**
 * The public URL of a token, from the package route (prefix and domain from config). Only the
 * token is in the URL, never a model id (rule E2).
 *
 * @internal
 */
final class PublicLinkUrl
{
    public static function for(string $token): string
    {
        return route(PublicLinksConfig::routeName().'show', ['token' => $token]);
    }
}
