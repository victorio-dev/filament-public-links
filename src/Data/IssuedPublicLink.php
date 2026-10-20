<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Data;

use Victorio\PublicLinks\Models\PublicLink;

/**
 * What issuing a link returns. `token` is the only place the plain token exists outside the
 * encrypted column: show or send it now, do not persist or log it (rules E1, E5).
 */
final readonly class IssuedPublicLink
{
    public function __construct(
        public string $url,
        public string $token,
        public PublicLink $link,
    ) {}
}
