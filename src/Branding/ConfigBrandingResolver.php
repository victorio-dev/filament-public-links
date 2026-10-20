<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Branding;

use Victorio\PublicLinks\Contracts\BrandingResolver;
use Victorio\PublicLinks\Data\Branding;
use Victorio\PublicLinks\Models\PublicLink;
use Victorio\PublicLinks\Support\PublicLinksConfig;

/**
 * The default resolver: the same branding for every link, from `filament-public-links.branding`.
 * A per-tenant resolver can fall back to it for the values it does not override.
 */
final class ConfigBrandingResolver implements BrandingResolver
{
    public function resolve(?PublicLink $link): Branding
    {
        $appName = config('app.name');

        return Branding::make(
            name: PublicLinksConfig::brandingString('name') ?? (is_string($appName) ? $appName : ''),
            logoUrl: PublicLinksConfig::brandingString('logo_url'),
            primaryColor: PublicLinksConfig::brandingString('primary_color'),
            footerText: PublicLinksConfig::brandingString('footer_text'),
            contactEmail: PublicLinksConfig::brandingString('contact_email'),
            showPoweredBy: (bool) config(PublicLinksConfig::KEY.'.branding.show_powered_by', false),
        );
    }
}
