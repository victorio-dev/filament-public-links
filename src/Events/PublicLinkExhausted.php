<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Events;

use Illuminate\Queue\SerializesModels;
use Victorio\PublicLinks\Models\PublicLink;

/**
 * The use that reached the usage limit was recorded. Fired exactly once per link.
 */
final class PublicLinkExhausted
{
    use SerializesModels;

    public function __construct(public PublicLink $link) {}
}
