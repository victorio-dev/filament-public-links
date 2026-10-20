<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Filament\Support;

use Carbon\CarbonInterface;
use Filament\Support\Facades\FilamentTimezone;
use Victorio\PublicLinks\PublicLinksPlugin;

/**
 * Every time the package shows in the admin UI — table, access log, URL modal — goes through
 * here: Filament's display timezone, the app locale, and one format (the plugin's
 * dateTimeFormat(), else the translated default).
 *
 * @internal
 */
final class DisplayDate
{
    public static function pattern(): string
    {
        return PublicLinksPlugin::current()->getDateTimeFormat() ?? IssueLinkForm::label('date_time_format');
    }

    public static function format(?CarbonInterface $date): ?string
    {
        // The app locale is passed explicitly: App::setLocale() does not change Carbon's global
        // locale, which would leave month names in English on a Hungarian panel.
        return $date?->copy()
            ->locale(app()->getLocale())
            ->setTimezone(FilamentTimezone::get())
            ->translatedFormat(self::pattern());
    }
}
