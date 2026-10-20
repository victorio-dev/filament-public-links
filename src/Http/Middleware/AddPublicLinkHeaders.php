<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Victorio\PublicLinks\Support\PublicLinksConfig;

/**
 * Outermost on the public routes, so every answer gets the headers — 200, 404, 410, 419 and 429
 * alike. no-referrer keeps the token in the URL from leaking to third-party assets.
 *
 * @internal
 */
final class AddPublicLinkHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (PublicLinksConfig::responseHeaders() as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }
}
