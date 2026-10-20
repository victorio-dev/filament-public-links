<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Events;

use Illuminate\Queue\SerializesModels;
use Victorio\PublicLinks\Models\PublicLink;
use Victorio\PublicLinks\Models\PublicLinkAccess;

/**
 * A link was opened for the first time. Fired exactly once per link, even with concurrent opens.
 */
final class PublicLinkFirstOpened
{
    use SerializesModels;

    public function __construct(
        public PublicLink $link,
        public PublicLinkAccess $access,
    ) {}
}
