<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Blade;

use function Pest\Laravel\get;

use Victorio\PublicLinks\Data\Branding;
use Victorio\PublicLinks\Support\ColorContrast;
use Victorio\PublicLinks\Tests\Fixtures\IntRecord;
use Victorio\PublicLinks\Tests\Fixtures\Tenant;
use Victorio\PublicLinks\Tests\Fixtures\TenantBrandingResolver;

beforeEach(function (): void {
    TenantBrandingResolver::$received = [];
});

afterEach(function (): void {
    Filament::setTenant(null, isQuiet: true);
});

function revokedLinkToken(): string
{
    $issued = IntRecord::create(['name' => 'Quote'])->publicLink('view')->issue();
    $issued->link->revoke();

    return $issued->token;
}

it('renders the configured brand on the public pages', function (): void {
    config()->set('filament-public-links.branding', [
        'name' => 'Acme Ltd',
        'logo_url' => 'https://cdn.example.test/acme.svg',
        'primary_color' => '#1D4ED8',
        'footer_text' => 'Acme Ltd, 1 Main Street',
    ]);

    get('/p/'.revokedLinkToken())
        ->assertStatus(410)
        ->assertSee('<img class="fpl-logo" src="https://cdn.example.test/acme.svg" alt="Acme Ltd">', escape: false)
        ->assertSee('--fpl-primary: #1d4ed8;', escape: false)
        ->assertSee('--fpl-on-primary: #ffffff;', escape: false)
        ->assertSee('Acme Ltd, 1 Main Street')
        ->assertSee('<title>This link is no longer available. · Acme Ltd</title>', escape: false);
});

it('falls back to the app name and shows it as text without a logo', function (): void {
    config()->set('app.name', 'Fallback App');

    get('/p/'.revokedLinkToken())
        ->assertStatus(410)
        ->assertSee('<span>Fallback App</span>', escape: false)
        ->assertDontSee('<img', escape: false);
});

it('escapes the configured texts', function (): void {
    config()->set('filament-public-links.branding.name', '<script>alert("name")</script>');
    config()->set('filament-public-links.branding.footer_text', '<script>alert("footer")</script>');

    get('/p/'.revokedLinkToken())
        ->assertStatus(410)
        ->assertDontSee('<script>', escape: false)
        ->assertSee('&lt;script&gt;alert(&quot;name&quot;)&lt;/script&gt;', escape: false)
        ->assertSee('&lt;script&gt;alert(&quot;footer&quot;)&lt;/script&gt;', escape: false);
});

it('drops colours and logo URLs that could break out of their context', function (): void {
    config()->set('filament-public-links.branding.primary_color', 'red;}</style><script>alert(1)</script>');
    config()->set('filament-public-links.branding.logo_url', 'javascript:alert(1)');

    get('/p/'.revokedLinkToken())
        ->assertStatus(410)
        ->assertSee('--fpl-primary: '.Branding::DEFAULT_PRIMARY_COLOR.';', escape: false)
        ->assertDontSee('<script>', escape: false)
        ->assertDontSee('javascript:', escape: false)
        ->assertDontSee('<img', escape: false);

    expect(Branding::make('X', logoUrl: '//evil.test/logo.png')->logoUrl)->toBeNull()
        ->and(Branding::make('X', logoUrl: '/images/logo.png')->logoUrl)->toBe('/images/logo.png')
        ->and(Branding::make('X', contactEmail: 'not an address')->contactEmail)->toBeNull();
});

it('uses a resolver bound in config and hands it the link', function (): void {
    config()->set('filament-public-links.branding.resolver', TenantBrandingResolver::class);
    $tenant = Tenant::create(['name' => 'Acme']);
    Filament::setTenant($tenant, isQuiet: true);
    $issued = IntRecord::create(['name' => 'Quote'])->publicLink('view')->issue();
    $issued->link->revoke();
    Filament::setTenant(null, isQuiet: true);

    get('/p/'.$issued->token)
        ->assertStatus(410)
        ->assertSee('Tenant '.$tenant->id)
        ->assertSee('--fpl-primary: #f59e0b;', escape: false)
        ->assertSee('--fpl-on-primary: #000000;', escape: false);

    expect(TenantBrandingResolver::$received)->toHaveCount(1)
        ->and(TenantBrandingResolver::$received[0]?->id)->toBe($issued->link->id);
});

it('refuses a resolver class that does not implement the contract', function (): void {
    config()->set('filament-public-links.branding.resolver', Tenant::class);

    get('/p/'.revokedLinkToken())->assertStatus(500);
});

it('renders the 404 and 410 pages in the layout, in English and Hungarian', function (string $locale, string $notFound, string $revoked): void {
    // The app locale decides only without the browser's language (Symfony's test requests send one).
    config()->set('filament-public-links.locales.accept_language', false);
    app()->setLocale($locale);
    $token = revokedLinkToken();

    get('/p/'.str_repeat('A', 43))
        ->assertNotFound()
        ->assertSee('<html lang="'.$locale.'" dir="ltr">', escape: false)
        ->assertSee('<header class="fpl-header">', escape: false)
        ->assertSee('<main class="fpl-main" id="content">', escape: false)
        ->assertSee('<h1>'.$notFound.'</h1>', escape: false);

    get('/p/'.$token)
        ->assertStatus(410)
        ->assertSee('<footer class="fpl-footer">', escape: false)
        ->assertSee('<h1>'.$revoked.'</h1>', escape: false);
})->with([
    'English' => ['en', 'This link does not exist.', 'This link is no longer available.'],
    'Hungarian' => ['hu', 'Ez a link nem létezik.', 'Ez a link már nem érhető el.'],
]);

it('shows the "Powered by" line only when it is switched on', function (): void {
    get('/p/'.revokedLinkToken())->assertDontSee('Powered by');

    config()->set('filament-public-links.branding.show_powered_by', true);

    get('/p/'.revokedLinkToken())->assertSee('Powered by Victorio Public Links');
});

it('offers the contact address on 410 only, and only when it is set', function (): void {
    config()->set('filament-public-links.locales.accept_language', false);

    get('/p/'.revokedLinkToken())->assertDontSee('mailto:', escape: false);

    config()->set('filament-public-links.branding.contact_email', 'links@acme.test');

    get('/p/'.revokedLinkToken())
        ->assertStatus(410)
        ->assertSee('Need a new link? Write to <a href="mailto:links@acme.test">links@acme.test</a>.', escape: false);

    get('/p/'.str_repeat('A', 43))->assertNotFound()->assertDontSee('mailto:', escape: false);

    app()->setLocale('hu');
    get('/p/'.revokedLinkToken())
        ->assertSee('Új linkre van szüksége? Írjon ide: <a href="mailto:links@acme.test">links@acme.test</a>.', escape: false);
});

it('keeps the privacy headers on the branded pages', function (): void {
    config()->set('filament-public-links.branding.name', 'Acme Ltd');

    foreach ([get('/p/'.revokedLinkToken())->assertStatus(410), get('/p/'.str_repeat('A', 43))->assertNotFound()] as $response) {
        $response->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Acme Ltd');
        expect($response->headers->get('Cache-Control'))->toContain('no-store');
    }
});

it('gives handlers the layout with title, content and footer slots', function (): void {
    config()->set('filament-public-links.branding.name', 'Acme Ltd');

    $html = Blade::render(<<<'BLADE'
        <x-filament-public-links::layout page-title="Quote Q-7">
            <x-slot:title>Quote Q-7</x-slot:title>
            <p>Total: 100 EUR</p>
            <x-slot:footer>Questions? Call us.</x-slot:footer>
        </x-filament-public-links::layout>
        BLADE);

    expect($html)->toContain('<title>Quote Q-7 · Acme Ltd</title>')
        ->and($html)->toContain('<h1>Quote Q-7</h1>')
        ->and($html)->toContain('<p>Total: 100 EUR</p>')
        ->and($html)->toContain('<p>Questions? Call us.</p>');
});

it('meets WCAG AA contrast with the default palette and any brand colour', function (): void {
    $pairs = [
        'light text' => ['#111827', '#ffffff'],
        'light muted text' => ['#4b5563', '#ffffff'],
        'light footer text' => ['#4b5563', '#f9fafb'],
        'dark text' => ['#f3f4f6', '#111827'],
        'dark muted text' => ['#9ca3af', '#111827'],
        'dark footer text' => ['#9ca3af', '#1f2937'],
        'default header' => [ColorContrast::foregroundFor(Branding::DEFAULT_PRIMARY_COLOR), Branding::DEFAULT_PRIMARY_COLOR],
    ];

    foreach ($pairs as $name => [$text, $background]) {
        expect(ColorContrast::ratio($text, $background))->toBeGreaterThanOrEqual(4.5, $name);
    }

    mt_srand(20261020);
    foreach (range(1, 500) as $sample) {
        $brand = sprintf('#%06x', mt_rand(0, 0xFFFFFF));
        expect(ColorContrast::ratio(ColorContrast::foregroundFor($brand), $brand))->toBeGreaterThanOrEqual(4.5, $brand);
    }
});
