<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Events;

use Illuminate\Queue\SerializesModels;
use Victorio\PublicLinks\Models\PublicLink;

/**
 * A link was issued. The plain token is not part of the event (rule E5).
 */
final class PublicLinkCreated
{
    use SerializesModels;

    public function __construct(public PublicLink $link) {}
}
