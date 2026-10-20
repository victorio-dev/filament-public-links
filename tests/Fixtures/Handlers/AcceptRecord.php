<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Tests\Fixtures\Handlers;

use Symfony\Component\HttpFoundation\Response;
use Victorio\PublicLinks\Contracts\HandlesPublicLinkActions;
use Victorio\PublicLinks\Contracts\PublicLinkHandler;
use Victorio\PublicLinks\Data\PublicLinkVisit;

/**
 * An "accept" handler: opening is free, a successful accept spends a use.
 */
final class AcceptRecord implements HandlesPublicLinkActions, PublicLinkHandler
{
    public static int $calls = 0;

    public function show(PublicLinkVisit $visit): Response
    {
        self::$calls++;

        return response('<form method="post">Accept?</form>');
    }

    public function countsOpensAsUses(): bool
    {
        return false;
    }

    public function actions(): array
    {
        return ['accept'];
    }

    public function handleAction(string $action, PublicLinkVisit $visit): Response
    {
        self::$calls++;

        if (! $visit->link->recordUse()) {
            return response('Already accepted.', 409);
        }

        return response('Accepted.');
    }
}
