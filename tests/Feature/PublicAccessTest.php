<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\withHeaders;
use function Pest\Laravel\withServerVariables;
use function Pest\Laravel\withSession;

use Victorio\PublicLinks\Events\PublicLinkActionPerformed;
use Victorio\PublicLinks\Events\PublicLinkFirstOpened;
use Victorio\PublicLinks\Events\PublicLinkOpened;
use Victorio\PublicLinks\Models\PublicLinkAccess;
use Victorio\PublicLinks\Support\IpHasher;
use Victorio\PublicLinks\Support\TokenComparer;
use Victorio\PublicLinks\Tests\Fixtures\Handlers\AcceptRecord;
use Victorio\PublicLinks\Tests\Fixtures\Handlers\ShowRecord;
use Victorio\PublicLinks\Tests\Fixtures\IntRecord;
use Victorio\PublicLinks\Tests\Fixtures\RecordingTokenComparer;
use Victorio\PublicLinks\Tests\Fixtures\StrictCsrf;
use Victorio\PublicLinks\Tests\Fixtures\Tenant;
use Victorio\PublicLinks\Tests\Fixtures\UlidRecord;

afterEach(function (): void {
    Filament::setTenant(null, isQuiet: true);
});

/**
 * A well-formed token that belongs to no link.
 */
function unknownToken(): string
{
    return str_repeat('A', 43);
}

function publicUrl(string $token, ?string $action = null): string
{
    return '/p/'.$token.($action === null ? '' : '/'.$action);
}

/**
 * @return list<string>
 */
function outcomes(): array
{
    return array_values(PublicLinkAccess::query()->orderBy('id')->pluck('outcome')
        ->map(fn (mixed $outcome): string => is_string($outcome) ? $outcome : '')
        ->all());
}

it('hands a valid link and its model to the handler and logs the open', function (): void {
    Event::fake([PublicLinkOpened::class, PublicLinkFirstOpened::class]);
    $record = IntRecord::create(['name' => 'Quote 7']);
    $issued = $record->publicLink('view')->issue();

    get(publicUrl($issued->token))->assertOk()->assertSee('Record: Quote 7');

    $visit = ShowRecord::$lastVisit;
    $access = PublicLinkAccess::query()->sole();
    $link = $issued->link->fresh();

    expect($visit?->link->id)->toBe($issued->link->id)
        ->and($visit?->linkable->is($record))->toBeTrue()
        ->and($visit?->action)->toBeNull()
        ->and($access->public_link_id)->toBe($issued->link->id)
        ->and($access->outcome)->toBe('opened')
        ->and($link?->open_count)->toBe(1)
        ->and($link?->use_count)->toBe(1)
        ->and($link?->first_opened_at)->not->toBeNull();

    Event::assertDispatchedTimes(PublicLinkOpened::class, 1);
    Event::assertDispatchedTimes(PublicLinkFirstOpened::class, 1);
});

it('fires PublicLinkFirstOpened only on the first open', function (): void {
    Event::fake([PublicLinkOpened::class, PublicLinkFirstOpened::class]);
    $issued = IntRecord::create(['name' => 'Quote'])->publicLink('view')->issue();

    get(publicUrl($issued->token))->assertOk();
    get(publicUrl($issued->token))->assertOk();

    Event::assertDispatchedTimes(PublicLinkOpened::class, 2);
    Event::assertDispatchedTimes(PublicLinkFirstOpened::class, 1);
    expect($issued->link->fresh()?->open_count)->toBe(2);
});

it('answers an unknown token with 404 and logs it without a link', function (): void {
    get(publicUrl(unknownToken()))->assertNotFound()->assertSee('This link does not exist.');

    $access = PublicLinkAccess::query()->sole();

    expect($access->public_link_id)->toBeNull()
        ->and($access->outcome)->toBe('not_found')
        ->and(ShowRecord::$calls)->toBe(0);
});

it('takes the same steps for an unknown and an existing token', function (): void {
    $comparer = new RecordingTokenComparer;
    app()->instance(TokenComparer::class, $comparer);
    $issued = IntRecord::create(['name' => 'Quote'])->publicLink('view')->issue();

    get(publicUrl($issued->token))->assertOk();
    $afterHit = ['comparisons' => $comparer->calls, 'rows' => PublicLinkAccess::query()->count()];

    get(publicUrl(unknownToken()))->assertNotFound();
    $afterMiss = ['comparisons' => $comparer->calls - $afterHit['comparisons'],
        'rows' => PublicLinkAccess::query()->count() - $afterHit['rows']];

    // One constant-time comparison and one access row on either branch.
    expect($afterHit)->toBe(['comparisons' => 1, 'rows' => 1])
        ->and($afterMiss)->toBe(['comparisons' => 1, 'rows' => 1]);
});

it('answers an expired link with 410 without running the handler', function (): void {
    Carbon::setTestNow('2026-10-20 12:00:00');
    $issued = IntRecord::create(['name' => 'Quote'])->publicLink('view')->expiresIn(hours: 1)->issue();

    Carbon::setTestNow('2026-10-20 13:00:01');

    get(publicUrl($issued->token))->assertStatus(410)->assertSee('This link has expired.');

    expect(ShowRecord::$calls)->toBe(0)
        ->and(outcomes())->toBe(['expired']);
});

it('answers a revoked link with 410 without running the handler', function (): void {
    $issued = IntRecord::create(['name' => 'Quote'])->publicLink('view')->issue();
    $issued->link->revoke();

    get(publicUrl($issued->token))->assertStatus(410)->assertSee('This link is no longer available.');

    expect(ShowRecord::$calls)->toBe(0)
        ->and(outcomes())->toBe(['revoked']);
});

it('spends the last use on exactly one of two requests and answers the other with 410', function (): void {
    $issued = IntRecord::create(['name' => 'Quote'])->publicLink('view')->maxUses(1)->issue();

    get(publicUrl($issued->token))->assertOk();
    get(publicUrl($issued->token))->assertStatus(410)->assertSee('This link has already been used.');

    expect(ShowRecord::$calls)->toBe(1)
        ->and($issued->link->fresh()?->use_count)->toBe(1)
        ->and(outcomes())->toBe(['opened', 'exhausted']);
});

it('lets the handler decide: an accept link spends its use on the action, not on opening', function (): void {
    Event::fake([PublicLinkActionPerformed::class]);
    $issued = IntRecord::create(['name' => 'Quote'])->publicLink('accept')->maxUses(1)->issue();

    get(publicUrl($issued->token))->assertOk();
    get(publicUrl($issued->token))->assertOk();
    expect($issued->link->fresh()?->use_count)->toBe(0);

    post(publicUrl($issued->token, 'accept'))->assertOk()->assertSee('Accepted.');
    post(publicUrl($issued->token, 'accept'))->assertStatus(410);

    expect($issued->link->fresh()?->use_count)->toBe(1)
        ->and(outcomes())->toBe(['opened', 'opened', 'action', 'exhausted']);

    Event::assertDispatched(
        PublicLinkActionPerformed::class,
        fn (PublicLinkActionPerformed $event): bool => $event->action === 'accept' && $event->link->is($issued->link),
    );
});

it('hands an action handler the access row written before it ran', function (): void {
    $issued = IntRecord::create(['name' => 'Quote'])->publicLink('accept')->issue();

    withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->post(publicUrl($issued->token, 'accept'))->assertOk();

    $access = PublicLinkAccess::query()->sole();

    expect(AcceptRecord::$lastVisit?->access?->is($access))->toBeTrue()
        ->and($access->outcome)->toBe('action')
        ->and($access->action)->toBe('accept');
});

it('keeps the access row of an action whose handler throws', function (): void {
    AcceptRecord::$throw = true;
    $issued = IntRecord::create(['name' => 'Quote'])->publicLink('accept')->issue();

    post(publicUrl($issued->token, 'accept'))->assertServerError();

    expect(outcomes())->toBe(['action']);
});

it('hands a show handler the access row of the open', function (): void {
    $issued = IntRecord::create(['name' => 'Quote'])->publicLink('view')->issue();

    get(publicUrl($issued->token))->assertOk();

    expect(ShowRecord::$lastVisit?->access?->is(PublicLinkAccess::query()->sole()))->toBeTrue();
});

it('exposes the access log IP hash, byte for byte, for other packages', function (): void {
    $issued = IntRecord::create(['name' => 'Quote'])->publicLink('view')->issue();
    withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->get(publicUrl($issued->token))->assertOk();

    $derivedKey = hash_hmac('sha256', 'filament-public-links:ip-hash', (string) config('app.key'));

    expect(PublicLinkAccess::query()->sole()->ip_hash)
        ->toBe(IpHasher::hash('203.0.113.9'))
        ->toBe(hash_hmac('sha256', '203.0.113.9', $derivedKey))
        ->and(ShowRecord::$lastVisit?->request === null ? null : IpHasher::forRequest(ShowRecord::$lastVisit->request))
        ->toBe(IpHasher::hash('203.0.113.9'));

    config()->set('filament-public-links.access_log.ip_hash_key', 'shared-key');

    expect(IpHasher::hash('203.0.113.9'))->toBe(hash_hmac('sha256', '203.0.113.9', 'shared-key'));
});

it('never expires a permanent link', function (): void {
    config()->set('filament-public-links.defaults.allow_permanent', true);
    Carbon::setTestNow('2026-10-20 12:00:00');
    $issued = IntRecord::create(['name' => 'Quote'])->publicLink('view')->permanent()->issue();

    Carbon::setTestNow('2036-10-20 12:00:00');

    get(publicUrl($issued->token))->assertOk();
});

it('answers requests over the per-IP limit with 429 and does not log them', function (): void {
    config()->set('filament-public-links.rate_limits.per_ip', ['max_attempts' => 3, 'decay_seconds' => 60]);
    $issued = IntRecord::create(['name' => 'Quote'])->publicLink('view')->issue();

    foreach (range(1, 3) as $request) {
        get(publicUrl($issued->token))->assertOk();
    }

    get(publicUrl($issued->token))->assertStatus(429)->assertHeader('Referrer-Policy', 'no-referrer');

    expect(PublicLinkAccess::query()->count())->toBe(3)
        ->and(ShowRecord::$calls)->toBe(3);
});

it('limits unknown-token attempts more strictly, without logging the throttled ones', function (): void {
    config()->set('filament-public-links.rate_limits.misses_per_ip', ['max_attempts' => 2, 'decay_seconds' => 600]);

    get(publicUrl(unknownToken()))->assertNotFound();
    get(publicUrl(unknownToken()))->assertNotFound();
    get(publicUrl(unknownToken()))->assertStatus(429);

    expect(outcomes())->toBe(['not_found', 'not_found']);
});

it('resolves by token only, whatever tenant the request carries, and logs under the link tenant', function (): void {
    $tenantA = Tenant::create(['name' => 'Acme']);
    $tenantB = Tenant::create(['name' => 'Globex']);
    $recordA = IntRecord::create(['name' => 'Acme quote']);
    $recordB = IntRecord::create(['name' => 'Globex quote']);

    Filament::setTenant($tenantA, isQuiet: true);
    $linkA = $recordA->publicLink('view')->issue();
    Filament::setTenant($tenantB, isQuiet: true);
    $recordB->publicLink('view')->issue();

    // An admin of tenant B opens tenant A's link.
    get(publicUrl($linkA->token))->assertOk()->assertSee('Acme quote')->assertDontSee('Globex quote');

    expect(ShowRecord::$lastVisit?->link->id)->toBe($linkA->link->id)
        ->and(ShowRecord::$lastVisit?->linkable->is($recordA))->toBeTrue()
        ->and(PublicLinkAccess::query()->sole()->getAttribute('tenant_id'))->toBe((string) $tenantA->id);
});

it('answers an action the handler does not accept with 404', function (): void {
    $accept = IntRecord::create(['name' => 'Quote'])->publicLink('accept')->issue();
    $view = IntRecord::create(['name' => 'Quote'])->publicLink('view')->issue();

    post(publicUrl($accept->token, 'reject'))->assertNotFound();
    post(publicUrl($view->token, 'accept'))->assertNotFound();

    expect(outcomes())->toBe(['unknown_action', 'unknown_action'])
        ->and(PublicLinkAccess::query()->pluck('action')->all())->toBe(['reject', 'accept'])
        ->and(AcceptRecord::$calls)->toBe(0);
});

it('refuses a POST without a CSRF token with 419', function (): void {
    // Laravel 13's web group uses PreventRequestForgery, Laravel 12's ValidateCsrfToken.
    $csrf = class_exists('Illuminate\Foundation\Http\Middleware\PreventRequestForgery')
        ? 'Illuminate\Foundation\Http\Middleware\PreventRequestForgery'
        : ValidateCsrfToken::class;
    app()->bind($csrf, StrictCsrf::class);
    $issued = IntRecord::create(['name' => 'Quote'])->publicLink('accept')->issue();

    post(publicUrl($issued->token, 'accept'))->assertStatus(419);

    expect(AcceptRecord::$calls)->toBe(0)
        ->and($issued->link->fresh()?->use_count)->toBe(0);

    withSession(['_token' => 'csrf-test-token'])
        ->post(publicUrl($issued->token, 'accept'), ['_token' => 'csrf-test-token'])
        ->assertOk();
});

it('sends the privacy headers on 200, 404 and 410', function (): void {
    $active = IntRecord::create(['name' => 'Quote'])->publicLink('view')->issue();
    $revoked = IntRecord::create(['name' => 'Quote'])->publicLink('view')->issue();
    $revoked->link->revoke();

    $responses = [
        get(publicUrl($active->token))->assertOk(),
        get(publicUrl(unknownToken()))->assertNotFound(),
        get(publicUrl($revoked->token))->assertStatus(410),
    ];

    foreach ($responses as $response) {
        $response->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        expect($response->headers->get('Cache-Control'))->toContain('no-store');
    }
});

it('never puts the token, its hash or a model id into the body or the headers', function (): void {
    $record = UlidRecord::create(['name' => 'Quote']);
    $active = $record->publicLink('view')->issue();
    $revoked = $record->publicLink('view')->issue();
    $revoked->link->revoke();

    $cases = [
        [$active, get(publicUrl($active->token))->assertOk()],
        [$revoked, get(publicUrl($revoked->token))->assertStatus(410)],
    ];

    foreach ($cases as [$issued, $response]) {
        /** @var TestResponse<Symfony\Component\HttpFoundation\Response> $response */
        $exposed = $response->getContent().(string) $response->headers;

        foreach ([$issued->token, $issued->link->token_hash, $issued->link->id, (string) $record->id] as $secret) {
            expect(str_contains($exposed, $secret))->toBeFalse();
        }
    }

    $miss = get(publicUrl(unknownToken()))->assertNotFound();
    expect(str_contains($miss->getContent().(string) $miss->headers, unknownToken()))->toBeFalse();
});

it('logs an HMAC of the IP and a truncated user agent, never the raw IP', function (): void {
    config()->set('filament-public-links.access_log.ip_hash_key', 'test-ip-key');
    $issued = IntRecord::create(['name' => 'Quote'])->publicLink('view')->issue();

    withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->withHeaders(['User-Agent' => str_repeat('Mozilla/5.0 ', 40)])
        ->get(publicUrl($issued->token))
        ->assertOk();

    $access = PublicLinkAccess::query()->sole();

    expect($access->ip_hash)->toBe(hash_hmac('sha256', '203.0.113.9', 'test-ip-key'))
        ->and(mb_strlen((string) $access->user_agent))->toBe(255);

    foreach ([config('filament-public-links.tables.accesses'), config('filament-public-links.tables.links')] as $table) {
        foreach (DB::table((string) $table)->get() as $row) {
            expect(str_contains(implode('|', array_map(strval(...), array_filter((array) $row, is_scalar(...)))), '203.0.113.9'))
                ->toBeFalse();
        }
    }
});

it('derives the IP hash key from the app key when none is configured', function (): void {
    $issued = IntRecord::create(['name' => 'Quote'])->publicLink('view')->issue();

    withServerVariables(['REMOTE_ADDR' => '198.51.100.4'])->get(publicUrl($issued->token))->assertOk();

    $ipHash = PublicLinkAccess::query()->sole()->ip_hash;

    expect($ipHash)->toMatch('/^[0-9a-f]{64}$/')
        ->and($ipHash)->not->toBe(hash('sha256', '198.51.100.4'));
});

it('answers 410 when the model behind the link was deleted', function (): void {
    $record = IntRecord::create(['name' => 'Quote']);
    $issued = $record->publicLink('view')->issue();
    $record->delete();

    get(publicUrl($issued->token))->assertStatus(410);

    expect(ShowRecord::$calls)->toBe(0)
        ->and(outcomes())->toBe(['missing_target']);
});

it('rejects a malformed token at the route, before any lookup or log row', function (): void {
    get('/p/too-short')->assertNotFound();
    get('/p/'.str_repeat('A', 42).'!')->assertNotFound();

    expect(PublicLinkAccess::query()->count())->toBe(0);
});

it('builds link URLs from the package route', function (): void {
    $issued = IntRecord::create(['name' => 'Quote'])->publicLink('view')->issue();

    expect($issued->url)->toBe(route('public-links.show', ['token' => $issued->token]))
        ->and($issued->link->url())->toBe($issued->url);

    withHeaders([])->get($issued->url)->assertOk();
});
