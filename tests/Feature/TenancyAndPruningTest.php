<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Victorio\PublicLinks\Models\PublicLink;
use Victorio\PublicLinks\Models\PublicLinkAccess;
use Victorio\PublicLinks\Tests\Fixtures\IntRecord;
use Victorio\PublicLinks\Tests\Fixtures\Tenant;

afterEach(function (): void {
    Filament::setTenant(null, isQuiet: true);
});

it('stores the current Filament tenant as a string key', function (): void {
    $tenant = Tenant::create(['name' => 'Acme']);
    Filament::setTenant($tenant, isQuiet: true);

    $link = IntRecord::create(['name' => 'Quote'])->publicLink('view')->issue()->link;

    expect($link->fresh()?->getAttribute('tenant_id'))->toBe((string) $tenant->id);
});

it('stores no tenant without a current tenant or with tenancy disabled', function (): void {
    $record = IntRecord::create(['name' => 'Quote']);
    $withoutTenant = $record->publicLink('view')->issue()->link;

    Filament::setTenant(Tenant::create(['name' => 'Acme']), isQuiet: true);
    config()->set('filament-public-links.tenancy.enabled', false);
    $tenancyOff = $record->publicLink('view')->issue()->link;

    expect($withoutTenant->fresh()?->getAttribute('tenant_id'))->toBeNull()
        ->and($tenancyOff->fresh()?->getAttribute('tenant_id'))->toBeNull();
});

it('uses the configured tenant column', function (): void {
    expect(PublicLink::query()->getModel()->getTable())->toBe('victorio_public_links')
        ->and(Schema::hasColumn('victorio_public_links', 'tenant_id'))->toBeTrue()
        ->and(Schema::hasColumn('victorio_public_link_accesses', 'tenant_id'))->toBeTrue();
});

/**
 * Inserts an access row the way step 2's recorder will, for the pruning tests.
 */
function accessRow(PublicLink $link, string $accessedAt): PublicLinkAccess
{
    return PublicLinkAccess::create([
        'public_link_id' => $link->id,
        'outcome' => 'opened',
        'ip_hash' => hash_hmac('sha256', '203.0.113.7', 'test-key'),
        'user_agent' => 'Pest',
        'accessed_at' => Carbon::parse($accessedAt),
    ]);
}

it('prunes access rows older than prune_after_days and keeps newer ones', function (): void {
    Carbon::setTestNow('2026-10-20 12:00:00');
    config()->set('filament-public-links.access_log.prune_after_days', 90);
    $link = IntRecord::create(['name' => 'Quote'])->publicLink('view')->issue()->link;

    $old = accessRow($link, '2026-07-22 11:59:59');
    $kept = accessRow($link, '2026-07-22 12:00:01');

    Artisan::call('model:prune', ['--model' => [PublicLinkAccess::class]]);

    expect(PublicLinkAccess::query()->pluck('id')->all())->toBe([$kept->id])
        ->and($old->fresh())->toBeNull();
});

it('keeps expired and revoked links by default', function (): void {
    Carbon::setTestNow('2026-01-01 12:00:00');
    $record = IntRecord::create(['name' => 'Quote']);
    $record->publicLink('view')->expiresIn(days: 1)->issue();
    $record->publicLink('view')->issue()->link->revoke();

    Carbon::setTestNow('2026-12-31 12:00:00');
    Artisan::call('model:prune', ['--model' => [PublicLink::class]]);

    expect(PublicLink::query()->count())->toBe(2);
});

it('prunes links that ended more than prune_links_after_days ago, with their access rows', function (): void {
    config()->set('filament-public-links.defaults.prune_links_after_days', 30);
    Carbon::setTestNow('2026-01-01 12:00:00');
    $record = IntRecord::create(['name' => 'Quote']);

    $expiredLongAgo = $record->publicLink('view')->expiresIn(days: 1)->issue()->link;
    $revokedLongAgo = $record->publicLink('view')->issue()->link;
    $revokedLongAgo->revoke();
    accessRow($expiredLongAgo, '2026-01-01 12:30:00');

    Carbon::setTestNow('2026-03-01 12:00:00');
    $active = $record->publicLink('view')->issue()->link;
    $revokedRecently = $record->publicLink('view')->issue()->link;
    $revokedRecently->revoke();

    Artisan::call('model:prune', ['--model' => [PublicLink::class]]);

    expect(PublicLink::query()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$active->id, $revokedRecently->id])->sort()->values()->all())
        ->and(PublicLinkAccess::query()->count())->toBe(0);
});
