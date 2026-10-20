<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Handlers;

use Victorio\PublicLinks\Contracts\PublicLinkHandler;

/**
 * Convenience base for handlers: every open counts as a use. Override countsOpensAsUses() when
 * only an action should spend the usage limit.
 */
abstract class PublicLinkHandlerBase implements PublicLinkHandler
{
    public function countsOpensAsUses(): bool
    {
        return true;
    }
}
