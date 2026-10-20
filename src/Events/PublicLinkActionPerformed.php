<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Events;

use Illuminate\Queue\SerializesModels;
use Victorio\PublicLinks\Models\PublicLink;
use Victorio\PublicLinks\Models\PublicLinkAccess;

/**
 * A handler action (POST) finished with a non-error response.
 */
final class PublicLinkActionPerformed
{
    use SerializesModels;

    public function __construct(
        public PublicLink $link,
        public string $action,
        public PublicLinkAccess $access,
    ) {}
}
