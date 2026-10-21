<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Data;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Victorio\PublicLinks\Models\PublicLink;
use Victorio\PublicLinks\Models\PublicLinkAccess;

/**
 * What a handler receives: the resolved, usable link, the model behind it (already loaded), the
 * request, the action name for POSTs (null for GET), and the access row already written for this
 * request, so a handler can refer to it (e.g. a decision recording which request it came from).
 */
final readonly class PublicLinkVisit
{
    public function __construct(
        public PublicLink $link,
        public Model $linkable,
        public Request $request,
        public ?string $action = null,
        public ?PublicLinkAccess $access = null,
    ) {}
}
