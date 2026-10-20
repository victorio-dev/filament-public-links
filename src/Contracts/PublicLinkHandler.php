<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Contracts;

use Symfony\Component\HttpFoundation\Response;
use Victorio\PublicLinks\Data\PublicLinkVisit;

/**
 * The code behind a public link, registered per model and purpose:
 *
 *     PublicLinks::handle(Quote::class, 'view', ShowQuote::class);
 *
 * The package has already checked the token, expiry, revocation and usage limit, written the
 * access row and loaded the model before show() runs.
 */
interface PublicLinkHandler
{
    /**
     * Renders the page behind the link (GET).
     */
    public function show(PublicLinkVisit $visit): Response;

    /**
     * Whether every successful open spends one use of the link's usage limit. True for "view"
     * links; false when only an action counts (e.g. accepting a quote).
     */
    public function countsOpensAsUses(): bool;
}
