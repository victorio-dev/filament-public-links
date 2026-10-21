<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Victorio\PublicLinks\Models\PublicLink;

/**
 * The language of a public page: the link's own locale, then the browser's Accept-Language among
 * the supported locales (unless switched off), then the app locale. The 404 page has no link and
 * starts at the browser. Set before anything renders, so the 410 page and the handler use it.
 *
 * @internal
 */
final class PublicLocale
{
    public static function resolve(?PublicLink $link, Request $request): string
    {
        if ($link !== null && is_string($link->locale) && $link->locale !== '') {
            return $link->locale;
        }

        if (PublicLinksConfig::useAcceptLanguage()) {
            $preferred = self::fromAcceptLanguage($request, PublicLinksConfig::supportedLocales());

            if ($preferred !== null) {
                return $preferred;
            }
        }

        return App::getLocale();
    }

    public static function apply(?PublicLink $link, Request $request): void
    {
        App::setLocale(self::resolve($link, $request));
    }

    /**
     * "ltr" or "rtl", for the page's dir attribute.
     */
    public static function direction(string $locale): string
    {
        $language = strtolower((string) preg_replace('/[-_].*$/', '', $locale));

        return in_array($language, array_map(strtolower(...), PublicLinksConfig::rtlLocales()), true) ? 'rtl' : 'ltr';
    }

    /**
     * The best supported locale for the browser: an exact match ("pt-BR" → "pt_BR") first, then
     * the language alone ("de-AT" → "de", "pt" → "pt_BR"), in the browser's order of preference.
     * Null when nothing matches; the browser's own default is never forced on the page.
     *
     * @param  list<string>  $supported
     */
    private static function fromAcceptLanguage(Request $request, array $supported): ?string
    {
        if ($supported === [] || ! $request->headers->has('Accept-Language')) {
            return null;
        }

        $normalised = [];

        foreach ($supported as $locale) {
            $normalised[strtolower(str_replace('-', '_', $locale))] = $locale;
        }

        foreach ($request->getLanguages() as $language) {
            $language = strtolower(str_replace('-', '_', $language));

            if (isset($normalised[$language])) {
                return $normalised[$language];
            }

            $primary = explode('_', $language)[0];

            foreach ($normalised as $key => $locale) {
                if (explode('_', $key)[0] === $primary) {
                    return $locale;
                }
            }
        }

        return null;
    }
}
