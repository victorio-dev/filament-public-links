<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Support;

use InvalidArgumentException;
use Victorio\PublicLinks\Branding\ConfigBrandingResolver;
use Victorio\PublicLinks\Contracts\BrandingResolver;
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

    /**
     * Route name prefix, e.g. "public-links." for "public-links.show".
     */
    public static function routeName(): string
    {
        return self::string('route.name', 'public-links.');
    }

    /**
     * @return list<string|class-string>
     */
    public static function routeMiddleware(): array
    {
        $middleware = config(self::KEY.'.route.middleware', ['web']);

        return is_array($middleware) ? array_values(array_filter($middleware, is_string(...))) : ['web'];
    }

    /**
     * @return array{max_attempts: int, decay_seconds: int}
     */
    public static function rateLimit(string $name): array
    {
        $defaults = $name === 'misses_per_ip'
            ? ['max_attempts' => 10, 'decay_seconds' => 600]
            : ['max_attempts' => 60, 'decay_seconds' => 60];

        return [
            'max_attempts' => self::int("rate_limits.{$name}.max_attempts", $defaults['max_attempts']),
            'decay_seconds' => self::int("rate_limits.{$name}.decay_seconds", $defaults['decay_seconds']),
        ];
    }

    public static function userAgentMaxLength(): int
    {
        return max(1, self::int('access_log.user_agent_max_length', 255));
    }

    /**
     * @return array<string, string>
     */
    public static function responseHeaders(): array
    {
        $headers = config(self::KEY.'.response_headers', []);

        if (! is_array($headers)) {
            return [];
        }

        $clean = [];

        foreach ($headers as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $clean[$name] = $value;
            }
        }

        return $clean;
    }

    public static function brandingString(string $key): ?string
    {
        return self::nullableString('branding.'.$key);
    }

    /**
     * The configured resolver class, or the config-based default.
     *
     * @return class-string<BrandingResolver>
     */
    public static function brandingResolver(): string
    {
        $resolver = config(self::KEY.'.branding.resolver');

        if ($resolver === null || $resolver === '') {
            return ConfigBrandingResolver::class;
        }

        if (! is_string($resolver) || ! is_subclass_of($resolver, BrandingResolver::class)) {
            throw new InvalidArgumentException(sprintf(
                '%s.branding.resolver must be a class implementing %s.',
                self::KEY,
                BrandingResolver::class,
            ));
        }

        return $resolver;
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
