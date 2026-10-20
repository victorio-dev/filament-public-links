<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Data;

use Victorio\PublicLinks\Support\ColorContrast;

/**
 * How the public pages look. Built only through make(), which cleans every value: a colour that
 * is not #rgb/#rrggbb falls back to the default, a logo URL must be http(s) or start with "/", an
 * invalid e-mail address is dropped. A custom BrandingResolver cannot hand unsafe values to the
 * layout this way.
 */
final readonly class Branding
{
    public const string DEFAULT_PRIMARY_COLOR = '#1f2937';

    private function __construct(
        public string $name,
        public ?string $logoUrl,
        public string $primaryColor,
        public string $onPrimaryColor,
        public ?string $footerText,
        public ?string $contactEmail,
        public bool $showPoweredBy,
    ) {}

    public static function make(
        string $name,
        ?string $logoUrl = null,
        ?string $primaryColor = null,
        ?string $footerText = null,
        ?string $contactEmail = null,
        bool $showPoweredBy = false,
    ): self {
        $primary = ColorContrast::normalize($primaryColor) ?? self::DEFAULT_PRIMARY_COLOR;

        return new self(
            name: trim($name),
            logoUrl: self::safeUrl($logoUrl),
            primaryColor: $primary,
            onPrimaryColor: ColorContrast::foregroundFor($primary),
            footerText: self::nonEmpty($footerText),
            contactEmail: filter_var($contactEmail, FILTER_VALIDATE_EMAIL) === false ? null : $contactEmail,
            showPoweredBy: $showPoweredBy,
        );
    }

    private static function safeUrl(?string $url): ?string
    {
        $url = self::nonEmpty($url);

        if ($url === null) {
            return null;
        }

        $isRelative = str_starts_with($url, '/') && ! str_starts_with($url, '//');
        $isHttp = preg_match('#^https?://[^\s]+$#i', $url) === 1;

        return $isRelative || $isHttp ? $url : null;
    }

    private static function nonEmpty(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
