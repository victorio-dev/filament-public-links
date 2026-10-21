<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

use Victorio\PublicLinks\Filament\RelationManagers\PublicLinksRelationManager;
use Victorio\PublicLinks\Tests\Fixtures\Filament\Pages\EditIntRecord;
use Victorio\PublicLinks\Tests\Fixtures\IntRecord;
use Victorio\PublicLinks\Tests\Fixtures\User;
use Victorio\PublicLinks\Tests\Support\PseudoLocale;

/**
 * Every visible text of the package's pages comes from a translation key: with the pseudo
 * locale on, nothing but data may appear outside ⟦ ⟧ (docs/conventions.md).
 */
beforeEach(function (): void {
    config()->set('filament-public-links.branding.contact_email', 'links@acme.test');
    config()->set('filament-public-links.branding.footer_text', 'Acme Ltd · 1 Main Street');
    config()->set('filament-public-links.branding.show_powered_by', true);
    PseudoLocale::enable();
});

/**
 * Data the pages show besides translations: brand values from config, record data.
 *
 * @return list<string>
 */
function pageData(): array
{
    return [(string) config('app.name'), 'Acme Ltd · 1 Main Street', 'links@acme.test', 'Quote'];
}

it('has no hard-coded text on the 404 page', function (): void {
    $html = (string) get('/p/'.str_repeat('A', 43))->assertNotFound()->getContent();

    expect(PseudoLocale::untranslated($html, pageData()))->toBe([]);
});

it('has no hard-coded text on the 410 page', function (): void {
    $issued = IntRecord::create(['name' => 'Quote'])->publicLink('view')->issue();
    $issued->link->revoke();

    $html = (string) get('/p/'.$issued->token)->assertStatus(410)->getContent();

    expect(PseudoLocale::untranslated($html, pageData()))->toBe([])
        // The check itself works: the translations are marked.
        ->and($html)->toContain('⟦');
});

it('has no hard-coded text in the layout around a consumer\'s content', function (): void {
    $html = Blade::render('<x-filament-public-links::layout page-title="Quote"><x-slot:title>Quote</x-slot:title><p>Quote</p></x-filament-public-links::layout>');

    expect(PseudoLocale::untranslated($html, pageData()))->toBe([]);
});

it('flags text that does not come from a translation', function (): void {
    expect(PseudoLocale::untranslated('<p>⟦Translated⟧</p><p>Hard-coded</p><img alt="Logo">', []))
        ->toBe(['Hard-coded', 'Logo'])
        ->and(PseudoLocale::untranslated('<p>⟦Write to <a>links@acme.test</a>.⟧</p>', ['links@acme.test']))->toBe([])
        // Without its opening ⟦ the start of the sentence is hard-coded.
        ->and(PseudoLocale::untranslated('<p>Write to <a>x</a>.⟧</p>', ['x']))->toBe(['Write to']);
});

describe('in Filament', function (): void {
    beforeEach(function (): void {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'x']));
    });

    /**
     * Data in the admin pages: the record, the link's note, the times shown (formatted by the
     * package with a translated format), the browser names in the log, the locale names (intl).
     *
     * @return list<string>
     */
    function adminData(): array
    {
        return [...pageData(), 'Sent to Anna', 'Symfony', 'Deutsch', 'English', 'Español', 'Français', 'Italiano',
            'Magyar', 'Nederlands', 'Português (Brasil)',
            // Relative times come from Carbon's own translations (the suite runs in English).
            Carbon\CarbonImmutable::now()->diffForHumans()];
    }

    it('has no hard-coded text in the relation manager and its modals', function (): void {
        Carbon\Carbon::setTestNow('2026-10-21 12:00:00');
        $record = IntRecord::create(['name' => 'Quote']);
        $issued = $record->publicLink('view')->label('Sent to Anna')->issue();
        get('/p/'.$issued->token);

        $component = Livewire::test(PublicLinksRelationManager::class, ['ownerRecord' => $record, 'pageClass' => EditIntRecord::class]);
        $table = $component->html();
        $issueModal = $component->mountTableAction('issuePublicLink')->html();

        foreach (['table' => $table, 'issue modal' => $issueModal] as $html) {
            expect(PseudoLocale::untranslated($html, adminData()))->toBe([]);
        }
    });
});
