<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Filament\Support;

use Victorio\PublicLinks\PublicLinkRegistry;
use Victorio\PublicLinks\PublicLinksPlugin;

/**
 * The label of a purpose in the admin UI: the plugin's purposeLabels() first, then the registry
 * (label given to handle(), package translation, readable name).
 *
 * @internal
 */
final class PurposeLabel
{
    public static function for(string $morphClass, string $purpose): string
    {
        return PublicLinksPlugin::current()->getPurposeLabel($purpose)
            ?? app(PublicLinkRegistry::class)->labelFor($morphClass, $purpose);
    }
}
