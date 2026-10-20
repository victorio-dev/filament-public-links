<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Swap either model for a subclass of your own (rule D5). The package
    | resolves every model through these keys, never by class name.
    |
    */

    'models' => [
        'link' => Victorio\PublicLinks\Models\PublicLink::class,
        'access' => Victorio\PublicLinks\Models\PublicLinkAccess::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Table names
    |--------------------------------------------------------------------------
    */

    'tables' => [
        'links' => 'victorio_public_links',
        'accesses' => 'victorio_public_link_accesses',
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-tenancy
    |--------------------------------------------------------------------------
    |
    | When enabled and a Filament tenant is set, a new link stores the tenant's
    | key in this column (rule D9). Without a current tenant the column stays
    | null, so the package also works outside tenant-aware panels.
    |
    */

    'tenancy' => [
        'enabled' => true,
        'column' => 'tenant_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Public route
    |--------------------------------------------------------------------------
    |
    | Links look like https://your-app.test/{prefix}/{token}. No model id is
    | ever part of the URL (rule E2).
    |
    */

    'route' => [
        'prefix' => 'p',
        'domain' => null,
        'middleware' => ['web'],
        'name' => 'public-links.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limits
    |--------------------------------------------------------------------------
    |
    | Per client IP (rule E3). Unknown tokens count against the stricter
    | "misses" limiter as well.
    |
    */

    'rate_limits' => [
        'per_ip' => ['max_attempts' => 60, 'decay_seconds' => 60],
        'misses_per_ip' => ['max_attempts' => 10, 'decay_seconds' => 600],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tokens
    |--------------------------------------------------------------------------
    |
    | Random bytes per token; at least 32 (rule E1), lower values are rejected
    | at boot. Tokens are looked up by their SHA-256 hash, and an encrypted
    | copy (app key) lets admins copy a link again (ADR-0008). The hash
    | algorithm is deliberately not configurable.
    |
    */

    'tokens' => [
        'bytes' => 32,
    ],

    /*
    |--------------------------------------------------------------------------
    | Link defaults
    |--------------------------------------------------------------------------
    |
    | Every link expires: after ttl_days unless the issuer says otherwise, and
    | never later than max_ttl_days. allow_permanent unlocks ->permanent()
    | (no expiry at all). prune_links_after_days deletes expired and revoked
    | links that long after they ended (null = keep them).
    |
    */

    'defaults' => [
        'ttl_days' => 30,
        'max_ttl_days' => 365,
        'allow_permanent' => false,
        'max_uses' => null,
        'prune_links_after_days' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Access log
    |--------------------------------------------------------------------------
    |
    | One row per public request: outcome, HMAC-hashed IP, truncated user
    | agent, time (rules E4, E5). Rate-limited (429) requests are not logged.
    | Rows older than prune_after_days are removed by `model:prune`, which the
    | host app has to schedule (rule E6).
    |
    */

    'access_log' => [
        // Larastan only recognises the app's config/ directory; env() in a package config file is
        // what rule D6 allows.
        'ip_hash_key' => env('PUBLIC_LINKS_IP_HASH_KEY'), // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
        'user_agent_max_length' => 255,
        'prune_after_days' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    |
    | Used by the public layout and the 404/410 pages. Colours are #rgb or
    | #rrggbb (anything else falls back to the default); the logo URL must be
    | http(s) or start with "/". contact_email, when set, is offered on the
    | 410 page for asking for a new link.
    |
    | To brand per tenant, set `resolver` to a class implementing
    | Victorio\PublicLinks\Contracts\BrandingResolver; null uses the values
    | below. Prefer this to publishing the views, which stop receiving updates.
    |
    */

    'branding' => [
        'resolver' => null,
        'name' => null, // null = config('app.name')
        'logo_url' => null,
        'primary_color' => '#1f2937',
        'footer_text' => null,
        'contact_email' => null,
        'show_powered_by' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Response headers
    |--------------------------------------------------------------------------
    |
    | Sent with every public response. no-referrer keeps the token in the URL
    | from leaking to third-party assets.
    |
    */

    'response_headers' => [
        'Referrer-Policy' => 'no-referrer',
        'X-Robots-Tag' => 'noindex, nofollow',
        'Cache-Control' => 'no-store, private',
    ],

];
