<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Contracts;

use Victorio\PublicLinks\Data\Branding;
use Victorio\PublicLinks\Models\PublicLink;

/**
 * Decides how the public pages look. Bind your own through
 * `filament-public-links.branding.resolver` to brand per tenant: the link (and through it the
 * tenant and the model) is passed in. It is null on the 404 page, where no link was found.
 */
interface BrandingResolver
{
    public function resolve(?PublicLink $link): Branding;
}
