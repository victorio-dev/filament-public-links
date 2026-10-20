<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Support;

use Victorio\PublicLinks\Models\PublicLink;
use Victorio\PublicLinks\Models\PublicLinkAccess;

/**
 * Typed access to config/filament-public-links.php, so the rest of the package never reads an
 * untyped config value directly.
 *
 * @internal
 */
final class PublicLinksConfig
{
    public const string KEY = 'filament-public-links';

    /**
     * @return class-string<PublicLink>
     */
    public static function linkModel(): string
    {
        /** @var class-string<PublicLink> $model */
        $model = config(self::KEY.'.models.link', PublicLink::class);

        return $model;
    }

    /**
     * @return class-string<PublicLinkAccess>
     */
    public static function accessModel(): string
    {
        /** @var class-string<PublicLinkAccess> $model */
        $model = config(self::KEY.'.models.access', PublicLinkAccess::class);

        return $model;
    }

    public static function linksTable(): string
    {
        return self::string('tables.links', 'victorio_public_links');
    }

    public static function accessesTable(): string
    {
        return self::string('tables.accesses', 'victorio_public_link_accesses');
    }

    public static function tenancyEnabled(): bool
    {
        return (bool) config(self::KEY.'.tenancy.enabled', true);
    }

    public static function tenantColumn(): string
    {
        return self::string('tenancy.column', 'tenant_id');
    }

    public static function routePrefix(): string
    {
        return trim(self::string('route.prefix', 'p'), '/');
    }

    public static function routeDomain(): ?string
    {
        return self::nullableString('route.domain');
    }

    public static function tokenBytes(): int
    {
        return self::int('tokens.bytes', 32);
    }

    public static function defaultTtlDays(): int
    {
        return self::int('defaults.ttl_days', 30);
    }

    public static function maxTtlDays(): ?int
    {
        return self::nullableInt('defaults.max_ttl_days');
    }

    public static function allowPermanent(): bool
    {
        return (bool) config(self::KEY.'.defaults.allow_permanent', false);
    }

    public static function defaultMaxUses(): ?int
    {
        return self::nullableInt('defaults.max_uses');
    }

    public static function pruneLinksAfterDays(): ?int
    {
        return self::nullableInt('defaults.prune_links_after_days');
    }

    public static function pruneAccessesAfterDays(): int
    {
        return self::int('access_log.prune_after_days', 90);
    }

    private static function string(string $key, string $default): string
    {
        $value = config(self::KEY.'.'.$key, $default);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private static function nullableString(string $key): ?string
    {
        $value = config(self::KEY.'.'.$key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function int(string $key, int $default): int
    {
        $value = config(self::KEY.'.'.$key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    private static function nullableInt(string $key): ?int
    {
        $value = config(self::KEY.'.'.$key);

        return is_numeric($value) ? (int) $value : null;
    }
}
