<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Events;

use Illuminate\Queue\SerializesModels;
use Victorio\PublicLinks\Models\PublicLink;

/**
 * A link was revoked. Fired once per link, however often revoke() is called.
 */
final class PublicLinkRevoked
{
    use SerializesModels;

    public function __construct(public PublicLink $link) {}
}
