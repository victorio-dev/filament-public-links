<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Victorio\PublicLinks\Enums\AccessOutcome;
use Victorio\PublicLinks\Enums\PublicLinkStatus;
use Victorio\PublicLinks\Http\PublicLinkResponses;
use Victorio\PublicLinks\Models\PublicLink;
use Victorio\PublicLinks\Support\AccessRecorder;

/**
 * Revoked, expired or used-up links are answered with 410 and an access row; the handler never
 * runs.
 *
 * @internal
 */
final class EnsurePublicLinkIsUsable
{
    public function __construct(private readonly AccessRecorder $recorder) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $link = $request->attributes->get(ResolvePublicLink::ATTRIBUTE);

        if (! $link instanceof PublicLink) {
            return PublicLinkResponses::status(AccessOutcome::NotFound, 404);
        }

        $status = $link->status();

        if ($status !== PublicLinkStatus::Active) {
            $outcome = AccessOutcome::forStatus($status);
            $this->recorder->record($link, $outcome, $request);

            return PublicLinkResponses::status($outcome, 410, $link);
        }

        return $next($request);
    }
}
