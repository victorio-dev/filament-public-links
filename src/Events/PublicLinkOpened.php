<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Events;

use Illuminate\Queue\SerializesModels;
use Victorio\PublicLinks\Models\PublicLink;
use Victorio\PublicLinks\Models\PublicLinkAccess;

/**
 * A link was opened successfully (GET). Fired on every open.
 */
final class PublicLinkOpened
{
    use SerializesModels;

    public function __construct(
        public PublicLink $link,
        public PublicLinkAccess $access,
    ) {}
}
