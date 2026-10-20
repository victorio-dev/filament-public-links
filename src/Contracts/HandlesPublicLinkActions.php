<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Contracts;

use Symfony\Component\HttpFoundation\Response;
use Victorio\PublicLinks\Data\PublicLinkVisit;

/**
 * A handler that also accepts POST actions: POST /{prefix}/{token}/{action}. Requests are
 * CSRF-protected by the route's `web` middleware.
 */
interface HandlesPublicLinkActions
{
    /**
     * The action names this handler accepts; any other action is answered with 404.
     *
     * @return list<string>
     */
    public function actions(): array;

    /**
     * Performs the action. If a successful action is a use of the link, call
     * `$visit->link->recordUse()` and treat false as "already used up".
     */
    public function handleAction(string $action, PublicLinkVisit $visit): Response;
}
