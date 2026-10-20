<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use Victorio\PublicLinks\Enums\AccessOutcome;
use Victorio\PublicLinks\Http\PublicLinkResponses;
use Victorio\PublicLinks\Support\AccessRecorder;
use Victorio\PublicLinks\Support\LinkResolver;
use Victorio\PublicLinks\Support\PublicLinksConfig;

/**
 * Resolves the token to a link, or answers 404. A miss also counts against the stricter
 * misses_per_ip limiter; over that limit the answer is 429 and nothing is written (E4 exception).
 *
 * @internal
 */
final class ResolvePublicLink
{
    public const string ATTRIBUTE = 'filament-public-links.link';

    public function __construct(
        private readonly LinkResolver $resolver,
        private readonly AccessRecorder $recorder,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->route('token');
        $link = is_string($token) ? $this->resolver->resolve($token) : null;

        if ($link === null) {
            $limit = PublicLinksConfig::rateLimit('misses_per_ip');
            $key = 'filament-public-links:misses:'.$request->ip();

            if (RateLimiter::tooManyAttempts($key, $limit['max_attempts'])) {
                throw new ThrottleRequestsException('Too Many Attempts.');
            }

            RateLimiter::hit($key, $limit['decay_seconds']);
            $this->recorder->record(null, AccessOutcome::NotFound, $request);

            return PublicLinkResponses::status(AccessOutcome::NotFound, 404);
        }

        $request->attributes->set(self::ATTRIBUTE, $link);

        return $next($request);
    }
}
