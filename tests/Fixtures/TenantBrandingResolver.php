<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Tests\Fixtures;

use Victorio\PublicLinks\Contracts\BrandingResolver;
use Victorio\PublicLinks\Data\Branding;
use Victorio\PublicLinks\Models\PublicLink;

/**
 * A per-tenant resolver as a host would write one; remembers the links it was asked about.
 */
final class TenantBrandingResolver implements BrandingResolver
{
    /**
     * @var list<PublicLink|null>
     */
    public static array $received = [];

    public function resolve(?PublicLink $link): Branding
    {
        self::$received[] = $link;

        return Branding::make(
            name: 'Tenant '.($link?->getAttribute('tenant_id') ?? 'none'),
            primaryColor: '#f59e0b',
            contactEmail: 'tenant@example.test',
        );
    }
}
