<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Support;

use Illuminate\Support\Facades\URL;

/**
 * Builds the public URL of a token from the route config. Only the token is in the URL, never a
 * model id (rule E2). Step 2 of the design registers the route itself under the same prefix.
 *
 * @internal
 */
final class PublicLinkUrl
{
    public static function for(string $token): string
    {
        $path = PublicLinksConfig::routePrefix().'/'.$token;
        $domain = PublicLinksConfig::routeDomain();

        if ($domain === null) {
            return URL::to($path);
        }

        $scheme = parse_url(URL::to('/'), PHP_URL_SCHEME);

        return (is_string($scheme) ? $scheme : 'https').'://'.$domain.'/'.$path;
    }
}
