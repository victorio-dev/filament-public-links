<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Support;

/**
 * WCAG 2.x relative luminance and contrast ratio, for picking readable text on the brand colour.
 *
 * @internal
 */
final class ColorContrast
{
    // Pure black and white: for any background one of them reaches at least ~4.58:1. A softer
    // dark (e.g. #111827) drops below 4.5:1 on some mid-tone brand colours.
    public const string DARK_TEXT = '#000000';

    public const string LIGHT_TEXT = '#ffffff';

    /**
     * #rgb or #rrggbb, lower-cased and expanded to #rrggbb; null for anything else. Colours end up
     * inside a <style> block, where HTML escaping does not help, so nothing else gets through.
     */
    public static function normalize(?string $color): ?string
    {
        if ($color === null || preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color) !== 1) {
            return null;
        }

        $hex = strtolower(substr($color, 1));

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return '#'.$hex;
    }

    /**
     * Whichever of black and white contrasts more with the background; for any background that is
     * at least 4.5:1 (WCAG AA for normal text).
     */
    public static function foregroundFor(string $background): string
    {
        return self::ratio($background, self::DARK_TEXT) >= self::ratio($background, self::LIGHT_TEXT)
            ? self::DARK_TEXT
            : self::LIGHT_TEXT;
    }

    public static function ratio(string $first, string $second): float
    {
        $a = self::luminance($first);
        $b = self::luminance($second);

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    private static function luminance(string $color): float
    {
        $hex = substr(self::normalize($color) ?? '#000000', 1);
        $channels = [];

        foreach ([0, 2, 4] as $offset) {
            $value = hexdec(substr($hex, $offset, 2)) / 255;
            $channels[] = $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
