<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

use Victorio\PublicLinks\Events\PublicLinkCreated;
use Victorio\PublicLinks\Events\PublicLinkRevoked;
use Victorio\PublicLinks\Filament\RelationManagers\PublicLinksRelationManager;
use Victorio\PublicLinks\Models\PublicLink;
use Victorio\PublicLinks\Models\PublicLinkAccess;
use Victorio\PublicLinks\PublicLinkRegistry;
use Victorio\PublicLinks\PublicLinksPlugin;
use Victorio\PublicLinks\Tests\Fixtures\Filament\Pages\EditIntRecord;
use Victorio\PublicLinks\Tests\Fixtures\Handlers\ShowRecord;
use Victorio\PublicLinks\Tests\Fixtures\IntRecord;
use Victorio\PublicLinks\Tests\Fixtures\IntRecordPolicy;
use Victorio\PublicLinks\Tests\Fixtures\Tenant;
use Victorio\PublicLinks\Tests\Fixtures\UlidRecord;
use Victorio\PublicLinks\Tests\Fixtures\User;
use Victorio\PublicLinks\Tests\Fixtures\UuidRecord;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'x']));
});

afterEach(function (): void {
    Filament::setTenant(null, isQuiet: true);
});

/**
 * The relation manager under test. Returns a Livewire Testable, typed as mixed because Testable
 * is generic in Livewire 4 but not in Livewire 3, and the suite runs on both.
 */
function linksTable(Illuminate\Database\Eloquent\Model $owner): mixed
{
    return Livewire::test(PublicLinksRelationManager::class, [
        'ownerRecord' => $owner,
        'pageClass' => EditIntRecord::class,
    ]);
}

function signIn(string $name): User
{
    $user = User::create(['name' => $name, 'email' => $name.'@example.test', 'password' => 'x']);
    actingAs($user);

    return $user;
}

it('lists only the links of the owner record', function (): void {
    $quote = IntRecord::create(['name' => 'Quote']);
    $other = IntRecord::create(['name' => 'Other']);
    $mine = [$quote->publicLink('view')->issue()->link, $quote->publicLink('accept')->issue()->link];
    $theirs = $other->publicLink('view')->issue()->link;

    linksTable($quote)
        ->assertCanSeeTableRecords($mine)
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('works for owners with int, ULID and UUID keys', function (string $model): void {
    /** @var IntRecord|UlidRecord|UuidRecord $owner */
    $owner = $model::create(['name' => 'Owner']);
    $other = $model::create(['name' => 'Other']);
    $link = $owner->publicLink('view')->issue()->link;
    $foreign = $other->publicLink('view')->issue()->link;

    linksTable($owner)
        ->assertCanSeeTableRecords([$link])
        ->assertCanNotSeeTableRecords([$foreign]);
})->with([IntRecord::class, UlidRecord::class, UuidRecord::class]);

it('shows only the current tenant\'s links and gives new links the current tenant', function (): void {
    $acme = Tenant::create(['name' => 'Acme']);
    $globex = Tenant::create(['name' => 'Globex']);
    $quote = IntRecord::create(['name' => 'Quote']);

    Filament::setTenant($acme, isQuiet: true);
    $acmeLink = $quote->publicLink('view')->issue()->link;
    Filament::setTenant($globex, isQuiet: true);
    $globexLink = $quote->publicLink('view')->issue()->link;

    Filament::setTenant($acme, isQuiet: true);
    linksTable($quote)
        ->assertCanSeeTableRecords([$acmeLink])
        ->assertCanNotSeeTableRecords([$globexLink])
        ->callTableAction('issuePublicLink', data: ['purpose' => 'view', 'expiry' => '7'])
        ->assertHasNoErrors();

    $created = PublicLink::query()->latest('created_at')->latest('id')->firstOrFail();
    expect($created->getAttribute('tenant_id'))->toBe((string) $acme->id);
});

it('creates a link from the form and shows its URL once', function (): void {
    Event::fake([PublicLinkCreated::class]);
    Carbon::setTestNow('2026-10-20 12:00:00');
    $quote = IntRecord::create(['name' => 'Quote']);

    $component = linksTable($quote)
        ->callTableAction('issuePublicLink', data: [
            'purpose' => 'accept',
            'expiry' => '14',
            'max_uses' => '2',
            'label' => 'Sent to Anna',
        ])
        ->assertHasNoErrors();

    $link = $quote->publicLinks()->sole();

    expect($link->purpose)->toBe('accept')
        ->and($link->expires_at?->toDateTimeString())->toBe('2026-11-03 12:00:00')
        ->and($link->max_uses)->toBe(2)
        ->and($link->label)->toBe('Sent to Anna')
        ->and($link->created_by_id)->toBe((string) auth()->id());

    $component->assertMountedActionModalSee((string) $link->url())
        ->assertMountedActionModalSee(['Expires: Nov 3, 2026 12:00', 'Usage limit: 2 (used: 0)', 'Close'])
        ->assertMountedActionModalDontSee(['Create link', 'Leave empty for no limit.']);
    Event::assertDispatched(PublicLinkCreated::class);
});

it('never shows the URL of another record\'s link, even to a crafted request', function (): void {
    $quote = IntRecord::create(['name' => 'Quote']);
    $foreign = IntRecord::create(['name' => 'Other'])->publicLink('view')->issue();

    linksTable($quote)
        ->call('mountAction', 'issuePublicLink', ['link' => $foreign->link->getKey()], [
            'table' => true,
            'filamentPublicLinksUrl' => true,
        ])
        ->assertMountedActionModalDontSee($foreign->url);
});

it('never renders the token or its encrypted copy into the table', function (): void {
    $quote = IntRecord::create(['name' => 'Quote']);
    $issued = $quote->publicLink('view')->issue();

    $html = linksTable($quote)->html();

    expect($html)->not->toContain($issued->token)
        ->and($html)->not->toContain($issued->link->token_encrypted)
        ->and($html)->not->toContain($issued->link->token_hash)
        ->and($html)->not->toContain((string) $issued->link->url());
});

it('copies the right URL, decrypted only when asked for', function (): void {
    $quote = IntRecord::create(['name' => 'Quote']);
    $first = $quote->publicLink('view')->issue();
    $second = $quote->publicLink('view')->issue();

    linksTable($quote)
        ->assertDontSee($first->url)
        ->mountTableAction('copyPublicLink', $first->link)
        ->assertMountedActionModalSee($first->url)
        ->assertMountedActionModalDontSee($second->url);
});

it('revokes one link and several at once, firing PublicLinkRevoked', function (): void {
    Event::fake([PublicLinkRevoked::class]);
    $quote = IntRecord::create(['name' => 'Quote']);
    [$one, $two, $three] = [
        $quote->publicLink('view')->issue()->link,
        $quote->publicLink('view')->issue()->link,
        $quote->publicLink('view')->issue()->link,
    ];

    linksTable($quote)
        ->callTableAction('revokePublicLink', $one)
        ->callTableBulkAction('revokePublicLinks', [$two, $three]);

    expect([$one->fresh()?->isRevoked(), $two->fresh()?->isRevoked(), $three->fresh()?->isRevoked()])
        ->toBe([true, true, true]);
    Event::assertDispatchedTimes(PublicLinkRevoked::class, 3);
});

it('lets a user without update see the links but not create, copy or revoke', function (): void {
    Gate::policy(IntRecord::class, IntRecordPolicy::class);
    $quote = IntRecord::create(['name' => 'Quote']);
    $link = $quote->publicLink('view')->issue()->link;
    signIn('viewer');

    expect(PublicLinksRelationManager::canViewForRecord($quote, EditIntRecord::class))->toBeTrue();

    linksTable($quote)
        ->assertCanSeeTableRecords([$link])
        ->assertTableActionHidden('issuePublicLink')
        ->assertTableActionHidden('copyPublicLink', $link)
        ->assertTableActionHidden('revokePublicLink', $link)
        ->assertTableActionVisible('viewPublicLinkAccessLog', $link);

    // A hand-crafted request for the hidden actions is refused as well.
    linksTable($quote)
        ->call('mountAction', 'revokePublicLink', [], ['table' => true, 'recordKey' => $link->getKey()])
        ->call('callMountedAction');
    linksTable($quote)
        ->call('mountAction', 'issuePublicLink', [], ['table' => true])
        ->set('mountedActions.0.data', ['purpose' => 'view', 'expiry' => '7'])
        ->call('callMountedAction');

    expect($link->fresh()?->isRevoked())->toBeFalse()
        ->and($quote->publicLinks()->count())->toBe(1);
});

it('hides the links from a user without view', function (): void {
    Gate::policy(IntRecord::class, IntRecordPolicy::class);
    $quote = IntRecord::create(['name' => 'Quote']);
    signIn('stranger');

    expect(PublicLinksRelationManager::canViewForRecord($quote, EditIntRecord::class))->toBeFalse();
});

it('follows strict authorization when the owner model has no policy', function (): void {
    $quote = IntRecord::create(['name' => 'Quote']);

    expect(PublicLinksRelationManager::canViewForRecord($quote, EditIntRecord::class))->toBeTrue();

    Filament::getPanel('admin')->strictAuthorization();

    expect(PublicLinksRelationManager::canViewForRecord($quote, EditIntRecord::class))->toBeFalse();
});

it('offers only purposes registered for the model', function (): void {
    $quote = IntRecord::create(['name' => 'Quote']);

    linksTable($quote)
        ->mountTableAction('issuePublicLink')
        ->assertMountedActionModalSee(['view', 'accept']);

    linksTable($quote)
        ->callTableAction('issuePublicLink', data: ['purpose' => 'download', 'expiry' => '7'])
        ->assertHasTableActionErrors(['purpose']);

    expect($quote->publicLinks()->count())->toBe(0);
});

it('does not ask for the purpose when the model has only one', function (): void {
    $registry = new PublicLinkRegistry(app());
    $registry->handle(IntRecord::class, 'view', ShowRecord::class);
    app()->instance(PublicLinkRegistry::class, $registry);
    $quote = IntRecord::create(['name' => 'Quote']);

    linksTable($quote)
        ->mountTableAction('issuePublicLink')
        ->assertMountedActionModalDontSee('Purpose');

    linksTable($quote)
        ->callTableAction('issuePublicLink', data: ['expiry' => '7'])
        ->assertHasNoErrors();

    expect($quote->publicLinks()->sole()->purpose)->toBe('view');
});

it('shows the "never expires" switch only when permanent links are allowed', function (): void {
    $quote = IntRecord::create(['name' => 'Quote']);

    linksTable($quote)->mountTableAction('issuePublicLink')->assertMountedActionModalDontSee('Never expires');

    config()->set('filament-public-links.defaults.allow_permanent', true);

    linksTable($quote)
        ->mountTableAction('issuePublicLink')
        ->assertMountedActionModalSee('Never expires');

    linksTable($quote)
        ->callTableAction('issuePublicLink', data: ['purpose' => 'view', 'permanent' => true])
        ->assertHasNoErrors();

    expect($quote->publicLinks()->sole()->expires_at)->toBeNull();
});

it('refuses an expiry date beyond max_ttl_days', function (): void {
    $quote = IntRecord::create(['name' => 'Quote']);

    linksTable($quote)
        ->callTableAction('issuePublicLink', data: [
            'purpose' => 'view',
            'expiry' => 'custom',
            'expires_on' => now()->addDays(400)->toDateString(),
        ])
        ->assertHasTableActionErrors(['expires_on']);

    linksTable($quote)
        ->callTableAction('issuePublicLink', data: [
            'purpose' => 'view',
            'expiry' => 'custom',
            'expires_on' => now()->addDays(365)->toDateString(),
        ])
        ->assertHasNoErrors();

    expect($quote->publicLinks()->count())->toBe(1);
});

it('shows the access log without any IP data', function (): void {
    config()->set('filament-public-links.access_log.ip_hash_key', 'test-key');
    $quote = IntRecord::create(['name' => 'Quote']);
    $link = $quote->publicLink('view')->issue()->link;
    $access = PublicLinkAccess::create([
        'public_link_id' => $link->id,
        'outcome' => 'opened',
        'ip_hash' => hash_hmac('sha256', '203.0.113.9', 'test-key'),
        'user_agent' => 'Mozilla/5.0 (Pest)',
        'accessed_at' => now(),
    ]);

    linksTable($quote)
        ->mountTableAction('viewPublicLinkAccessLog', $link)
        ->assertMountedActionModalSee('Opened')
        ->assertMountedActionModalSee('Mozilla/5.0 (Pest)')
        ->assertMountedActionModalDontSee($access->ip_hash)
        ->assertMountedActionModalDontSee('203.0.113.9');
});

it('labels the table in English and Hungarian', function (string $locale, array $labels): void {
    app()->setLocale($locale);
    $quote = IntRecord::create(['name' => 'Quote']);
    $quote->publicLink('view')->issue();

    $component = linksTable($quote);

    foreach ($labels as $label) {
        $component->assertSee($label);
    }
})->with([
    'English' => ['en', ['Purpose', 'Status', 'Expires', 'Uses', 'Active', 'New public link']],
    'Hungarian' => ['hu', ['Cél', 'Állapot', 'Lejár', 'Használat', 'Aktív', 'Új nyilvános link']],
]);

it('issues a link from a resource page through IssuePublicLinkAction', function (): void {
    $quote = IntRecord::create(['name' => 'Quote']);

    $component = Livewire::test(EditIntRecord::class, ['record' => $quote->getKey()])
        ->callAction('issuePublicLink', data: ['purpose' => 'view', 'expiry' => '7'])
        ->assertHasNoErrors();

    $link = $quote->publicLinks()->sole();
    $component->assertMountedActionModalSee((string) $link->url());
});

it('shows expiry and usage in the "Copy link" modal, and "Never expires" for permanent links', function (): void {
    Carbon::setTestNow('2026-10-20 12:00:00');
    config()->set('filament-public-links.defaults.allow_permanent', true);
    $quote = IntRecord::create(['name' => 'Quote']);
    $limited = $quote->publicLink('view')->expiresIn(days: 7)->maxUses(3)->issue()->link;
    $limited->recordUse();
    $permanent = $quote->publicLink('view')->permanent()->issue()->link;

    linksTable($quote)
        ->mountTableAction('copyPublicLink', $limited)
        ->assertMountedActionModalSee(['Expires: Oct 27, 2026 12:00', 'Usage limit: 3 (used: 1)']);

    linksTable($quote)
        ->mountTableAction('copyPublicLink', $permanent)
        ->assertMountedActionModalSee('Never expires')
        ->assertMountedActionModalDontSee('Usage limit');
});

it('tells every links table on the page to refresh after a link is created or revoked elsewhere', function (): void {
    $quote = IntRecord::create(['name' => 'Quote']);
    $table = linksTable($quote);

    Livewire::test(EditIntRecord::class, ['record' => $quote->getKey()])
        ->callAction('issuePublicLink', data: ['purpose' => 'view', 'expiry' => '7'])
        ->assertDispatched(PublicLinksRelationManager::LINKS_CHANGED);

    $link = $quote->publicLinks()->sole();

    // The table that was already on the page picks the new link up on the event, no reload.
    $table->dispatch(PublicLinksRelationManager::LINKS_CHANGED)
        ->assertCanSeeTableRecords([$link]);

    linksTable($quote)
        ->callTableAction('revokePublicLink', $link)
        ->assertDispatched(PublicLinksRelationManager::LINKS_CHANGED);

    $table->dispatch(PublicLinksRelationManager::LINKS_CHANGED)
        ->assertSee('Revoked');
});

it('shows times in Filament\'s display timezone and the same format in the table and the log', function (): void {
    FilamentTimezone::set('Europe/Budapest');
    Carbon::setTestNow('2026-10-20 12:00:00'); // UTC, 14:00 in Budapest
    $quote = IntRecord::create(['name' => 'Quote']);
    $link = $quote->publicLink('view')->expiresIn(days: 7)->issue()->link;
    PublicLinkAccess::create([
        'public_link_id' => $link->id,
        'outcome' => 'opened',
        'ip_hash' => str_repeat('a', 64),
        'accessed_at' => now(),
    ]);

    // Created on Oct 20 (CEST, UTC+2); expires on Oct 27, after the switch to CET (UTC+1).
    linksTable($quote)
        ->assertSee('Oct 27, 2026 13:00')   // expires
        ->assertSee('Oct 20, 2026 14:00')   // created
        ->mountTableAction('viewPublicLinkAccessLog', $link)
        ->assertMountedActionModalSee('Oct 20, 2026 14:00');

    app()->setLocale('hu');
    linksTable($quote)->assertSee('2026. okt. 27. 13:00');

    FilamentTimezone::set(null);
});

it('uses the plugin\'s date format everywhere when one is set', function (): void {
    Carbon::setTestNow('2026-10-20 12:00:00');
    Filament::getPanel('admin')->plugin(PublicLinksPlugin::make()->dateTimeFormat('d/m/Y H:i'));
    $quote = IntRecord::create(['name' => 'Quote']);
    $link = $quote->publicLink('view')->expiresIn(days: 7)->issue()->link;

    linksTable($quote)
        ->assertSee('27/10/2026 12:00')
        ->mountTableAction('copyPublicLink', $link)
        ->assertMountedActionModalSee('Expires: 27/10/2026 12:00');
});

it('keeps a custom expiry date valid through that day in the display timezone', function (): void {
    FilamentTimezone::set('Europe/Budapest');
    Carbon::setTestNow('2026-10-20 12:00:00');
    $quote = IntRecord::create(['name' => 'Quote']);

    linksTable($quote)
        ->callTableAction('issuePublicLink', data: ['purpose' => 'view', 'expiry' => 'custom', 'expires_on' => '2026-10-23'])
        ->assertHasNoErrors();
    linksTable($quote)
        ->callTableAction('issuePublicLink', data: ['purpose' => 'view', 'expiry' => 'custom', 'expires_on' => '2026-11-10'])
        ->assertHasNoErrors();

    // End of the day in Budapest: CEST (UTC+2) before Oct 25, CET (UTC+1) after.
    expect($quote->publicLinks()->orderBy('expires_at')->pluck('expires_at')->map->toDateTimeString()->all())
        ->toBe(['2026-10-23 21:59:59', '2026-11-10 22:59:59']);

    FilamentTimezone::set(null);
});

it('labels purposes: plugin first, then the label given to handle(), then translations, then the name', function (): void {
    $registry = new PublicLinkRegistry(app());
    $registry->handle(IntRecord::class, 'view', ShowRecord::class);
    $registry->handle(IntRecord::class, 'sign-contract', ShowRecord::class);
    $registry->handle(IntRecord::class, 'accept', ShowRecord::class, 'Accept the quote');
    $registry->handle(IntRecord::class, 'download', ShowRecord::class, 'filament-public-links::filament-public-links.purposes.view');
    app()->instance(PublicLinkRegistry::class, $registry);

    expect($registry->labelFor(IntRecord::class, 'view'))->toBe('View')                     // package translation
        ->and($registry->labelFor(IntRecord::class, 'sign-contract'))->toBe('Sign contract') // readable name
        ->and($registry->labelFor(IntRecord::class, 'accept'))->toBe('Accept the quote')     // plain label
        ->and($registry->labelFor(IntRecord::class, 'download'))->toBe('View');              // translation key

    app()->setLocale('hu');
    expect($registry->labelFor(IntRecord::class, 'view'))->toBe('Megtekintés');
    app()->setLocale('en');

    $quote = IntRecord::create(['name' => 'Quote']);
    $quote->publicLink('accept')->issue();

    linksTable($quote)->assertSee('Accept the quote');

    Filament::getPanel('admin')->plugin(PublicLinksPlugin::make()->purposeLabels(['accept' => 'Plugin label']));

    linksTable($quote)->assertSee('Plugin label')->assertDontSee('Accept the quote');
});

it('shows the last open relative to now, in the app locale', function (): void {
    Carbon::setTestNow('2026-10-20 12:00:00');
    $quote = IntRecord::create(['name' => 'Quote']);
    $link = $quote->publicLink('view')->issue()->link;
    $link->markOpened();
    Carbon::setTestNow('2026-10-20 12:03:00');

    linksTable($quote)->assertSee('3 minutes ago');

    app()->setLocale('hu');
    linksTable($quote)->assertSee('3 perce');
});

it('shows the link note under the purpose and in the access log heading', function (): void {
    $quote = IntRecord::create(['name' => 'Quote']);
    $noted = $quote->publicLink('view')->label('Sent to Anna')->issue()->link;
    $plain = $quote->publicLink('view')->issue()->link;

    linksTable($quote)->assertSee('Sent to Anna');

    linksTable($quote)
        ->mountTableAction('viewPublicLinkAccessLog', $noted)
        ->assertMountedActionModalSee('Access log — Sent to Anna');

    linksTable($quote)
        ->mountTableAction('viewPublicLinkAccessLog', $plain)
        ->assertMountedActionModalSee('Access log')
        ->assertMountedActionModalDontSee('Sent to Anna');

    app()->setLocale('hu');
    linksTable($quote)
        ->mountTableAction('viewPublicLinkAccessLog', $noted)
        ->assertMountedActionModalSee('Hozzáférési napló — Sent to Anna');
});

it('escapes the link note', function (): void {
    $quote = IntRecord::create(['name' => 'Quote']);
    $link = $quote->publicLink('view')->label('<script>alert(1)</script>')->issue()->link;

    linksTable($quote)
        ->assertDontSee('<script>alert(1)</script>', escape: false)
        ->mountTableAction('viewPublicLinkAccessLog', $link)
        ->assertMountedActionModalDontSee('<script>alert(1)</script>', escape: false);
});

describe('issuing for another model', function (): void {
    function editPage(IntRecord $record): mixed
    {
        return Livewire::test(EditIntRecord::class, ['record' => $record->getKey()]);
    }

    it('issues through issueUsing() with the form\'s settings and shows the URL', function (): void {
        Carbon::setTestNow('2026-10-20 12:00:00');
        $quote = IntRecord::create(['name' => 'Quote']);

        $page = editPage($quote)->callAction('issueForCopy', data: ['expiry' => '7', 'locale' => 'de'])->assertHasNoErrors();

        $copy = UlidRecord::query()->where('name', 'Copy of Quote')->sole();
        $link = $copy->publicLinks()->sole();

        expect($quote->publicLinks()->count())->toBe(0)
            ->and($link->locale)->toBe('de')
            ->and($link->expires_at?->toDateTimeString())->toBe('2026-10-27 12:00:00')
            ->and($link->max_uses)->toBeNull()
            ->and($link->created_by_id)->toBe((string) auth()->id());

        $page->assertMountedActionModalSee((string) $link->url());
    });

    it('uses its own headings for the form and the URL step', function (): void {
        $record = IntRecord::create(['name' => 'Quote']);

        editPage($record)->mountAction('issueForCopy')->assertMountedActionModalSee('Share a copy');

        editPage($record)->callAction('issueForCopy', data: ['expiry' => '7'])
            ->assertMountedActionModalSee('Copy link')
            ->assertMountedActionModalDontSee(['Share a copy', 'Public link']);
    });

    it('keeps the default headings without them', function (): void {
        $record = IntRecord::create(['name' => 'Quote']);

        editPage($record)->mountAction('issuePublicLink')->assertMountedActionModalSee('New public link');
        editPage($record)->callAction('issuePublicLink', data: ['purpose' => 'view', 'expiry' => '7'])
            ->assertMountedActionModalSee('Public link');
    });

    it('shows only the chosen fields', function (): void {
        editPage(IntRecord::create(['name' => 'Quote']))
            ->mountAction('issueForCopy')
            ->assertMountedActionModalSee(['Expires in', 'Language'])
            ->assertMountedActionModalDontSee(['Purpose', 'Usage limit', 'Note']);
    });

    it('shows a URL only when linkOwnedUsing() accepts the link, even to a crafted request', function (): void {
        $quote = IntRecord::create(['name' => 'Quote']);
        $foreign = UlidRecord::create(['name' => 'Copy of Other'])->publicLink('view')->issue();

        editPage($quote)
            ->call('mountAction', 'issueForCopy', ['link' => $foreign->link->getKey()], ['filamentPublicLinksUrl' => true])
            ->assertMountedActionModalDontSee($foreign->url);
    });
});
