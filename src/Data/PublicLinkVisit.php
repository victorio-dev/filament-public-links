<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Data;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Victorio\PublicLinks\Models\PublicLink;

/**
 * What a handler receives: the resolved, usable link, the model behind it (already loaded), the
 * request, and the action name for POSTs (null for GET).
 */
final readonly class PublicLinkVisit
{
    public function __construct(
        public PublicLink $link,
        public Model $linkable,
        public Request $request,
        public ?string $action = null,
    ) {}
}
