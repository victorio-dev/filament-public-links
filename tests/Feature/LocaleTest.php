<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\withHeaders;

use Victorio\PublicLinks\Exceptions\InvalidPublicLink;
use Victorio\PublicLinks\Filament\RelationManagers\PublicLinksRelationManager;
use Victorio\PublicLinks\Tests\Fixtures\Filament\Pages\EditIntRecord;
use Victorio\PublicLinks\Tests\Fixtures\Handlers\ShowRecord;
use Victorio\PublicLinks\Tests\Fixtures\IntRecord;
use Victorio\PublicLinks\Tests\Fixtures\User;

/**
 * The language a handler and the 410 page see: what App::getLocale() is while ShowRecord runs.
 */
function localeOfOpen(string $token, ?string $acceptLanguage = null): string
{
    $seen = null;
    ShowRecord::$lastVisit = null;

    ($acceptLanguage === null ? get('/p/'.$token) : withHeaders(['Accept-Language' => $acceptLanguage])->get('/p/'.$token))
        ->assertOk();

    return App::getLocale();
}

describe('a page with a link', function (): void {
    it('uses the link\'s own locale first', function (): void {
        $issued = IntRecord::create(['name' => 'Quote'])->publicLink('view')->locale('hu')->issue();

        expect(localeOfOpen($issued->token, 'de-DE,de;q=0.9'))->toBe('hu')
            ->and($issued->link->fresh()?->locale)->toBe('hu');
    });

    it('then the browser\'s language among the supported ones', function (string $header, string $expected): void {
        $issued = IntRecord::create(['name' => 'Quote'])->publicLink('view')->issue();

        expect(localeOfOpen($issued->token, $header))->toBe($expected);
    })->with([
        'an exact match' => ['fr-FR,fr;q=0.9,en;q=0.8', 'fr'],
        'a regional variant' => ['de-AT', 'de'],
        'a language whose supported locale is regional' => ['pt', 'pt_BR'],
        'a region tag' => ['pt-BR', 'pt_BR'],
        'the first supported in order of preference' => ['ja,nl;q=0.8,de;q=0.5', 'nl'],
    ]);

    it('then the app locale', function (): void {
        // Without a header of its own, a test request still sends Symfony's default "en-us".
        App::setLocale('en');
        $issued = IntRecord::create(['name' => 'Quote'])->publicLink('view')->issue();

        expect(localeOfOpen($issued->token, 'ja,zh;q=0.5'))->toBe('en')
            ->and(localeOfOpen($issued->token))->toBe('en');
    });

    it('ignores the browser when switched off', function (): void {
        config()->set('filament-public-links.locales.accept_language', false);
        App::setLocale('en');
        $issued = IntRecord::create(['name' => 'Quote'])->publicLink('view')->issue();

        expect(localeOfOpen($issued->token, 'de'))->toBe('en');
    });

    it('shows the 410 page in the link\'s language', function (): void {
        $issued = IntRecord::create(['name' => 'Quote'])->publicLink('view')->locale('de')->issue();
        $issued->link->revoke();

        withHeaders(['Accept-Language' => 'fr'])->get('/p/'.$issued->token)
            ->assertStatus(410)
            ->assertSee('Dieser Link ist nicht mehr verfügbar.')
            ->assertSee('<html lang="de" dir="ltr">', false);
    });
});

describe('the 404 page', function (): void {
    it('uses the browser\'s language', function (): void {
        withHeaders(['Accept-Language' => 'es-ES'])->get('/p/'.str_repeat('A', 43))
            ->assertNotFound()
            ->assertSee('Este enlace no existe.');
    });

    it('then the app locale', function (): void {
        App::setLocale('en');

        withHeaders(['Accept-Language' => 'ja'])->get('/p/'.str_repeat('A', 43))
            ->assertNotFound()
            ->assertSee('This link does not exist.');
    });

    it('ignores the browser when switched off', function (): void {
        config()->set('filament-public-links.locales.accept_language', false);
        App::setLocale('en');

        withHeaders(['Accept-Language' => 'es'])->get('/p/'.str_repeat('A', 43))->assertSee('This link does not exist.');
    });
});

it('writes right-to-left languages right to left', function (): void {
    $rtl = IntRecord::create(['name' => 'Quote'])->publicLink('view')->locale('ar')->issue();
    $rtl->link->revoke();

    get('/p/'.$rtl->token)->assertStatus(410)->assertSee('<html lang="ar" dir="rtl">', false);
    withHeaders(['Accept-Language' => 'en'])->get('/p/'.str_repeat('A', 43))->assertSee('dir="ltr"', false);
});

it('refuses a malformed locale', function (string $locale): void {
    expect(fn () => IntRecord::create(['name' => 'Quote'])->publicLink('view')->locale($locale)->issue())
        ->toThrow(InvalidPublicLink::class);
})->with(['english', 'e', 'en_US_POSIX_extra', '<b>']);

describe('the Filament form', function (): void {
    beforeEach(function (): void {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'x']));
    });

    /**
     * @param  array<string, mixed>  $data
     */
    function issueFromForm(IntRecord $record, array $data = []): void
    {
        Livewire::test(PublicLinksRelationManager::class, ['ownerRecord' => $record, 'pageClass' => EditIntRecord::class])
            ->callTableAction('issuePublicLink', data: ['purpose' => 'view', 'expiry' => '7', ...$data])
            ->assertHasNoErrors();
    }

    it('proposes the record\'s language', function (): void {
        IntRecord::$preferredLocale = 'de';
        $record = IntRecord::create(['name' => 'Quote']);

        issueFromForm($record);

        expect($record->publicLinks()->sole()->locale)->toBe('de');
    });

    it('proposes the app language when the record has none', function (): void {
        App::setLocale('hu');
        $record = IntRecord::create(['name' => 'Quote']);

        issueFromForm($record);

        expect($record->publicLinks()->sole()->locale)->toBe('hu');
    });

    it('lets the admin pick a language or leave it to the browser', function (): void {
        $record = IntRecord::create(['name' => 'Quote']);

        issueFromForm($record, ['locale' => 'it']);
        issueFromForm($record, ['locale' => null]);

        expect($record->publicLinks()->orderBy('created_at')->pluck('locale')->sort()->values()->all())->toBe([null, 'it']);
    });

    it('offers the supported languages, named in their own language', function (): void {
        Livewire::test(PublicLinksRelationManager::class, ['ownerRecord' => IntRecord::create(['name' => 'Quote']), 'pageClass' => EditIntRecord::class])
            ->mountTableAction('issuePublicLink')
            ->assertMountedActionModalSee(['Deutsch', 'Magyar', 'Português (Brasil)', 'Automatic (the visitor\'s browser)']);
    });
});
