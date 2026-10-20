<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Victorio\PublicLinks\Events\PublicLinkCreated;
use Victorio\PublicLinks\Exceptions\ExpiryTooFar;
use Victorio\PublicLinks\Exceptions\InsecureTokenConfiguration;
use Victorio\PublicLinks\Exceptions\InvalidPublicLink;
use Victorio\PublicLinks\Exceptions\PermanentLinksDisabled;
use Victorio\PublicLinks\Models\PublicLink;
use Victorio\PublicLinks\PublicLinksServiceProvider;
use Victorio\PublicLinks\Tests\Fixtures\IntRecord;
use Victorio\PublicLinks\Tests\Fixtures\UlidRecord;
use Victorio\PublicLinks\Tests\Fixtures\User;
use Victorio\PublicLinks\Tests\Fixtures\UuidRecord;

/**
 * @return array<string, array{0: Closure(): Model}>
 */
function keyTypes(): array
{
    return [
        'int key' => [fn (): Model => IntRecord::create(['name' => 'Quote 1'])],
        'ULID key' => [fn (): Model => UlidRecord::create(['name' => 'Quote 1'])],
        'UUID key' => [fn (): Model => UuidRecord::create(['name' => 'Quote 1'])],
    ];
}

it('issues a link to a model of any key type and finds it through the relation', function (Closure $make): void {
    /** @var IntRecord|UlidRecord|UuidRecord $record */
    $record = $make();

    $issued = $record->publicLink('view')->issue();

    expect($issued->link->linkable_id)->toBe((string) $record->getKey())
        ->and($issued->link->linkable_type)->toBe($record->getMorphClass())
        ->and($record->publicLinks()->pluck('id')->all())->toBe([$issued->link->id])
        ->and($issued->link->fresh()?->linkable?->is($record))->toBeTrue();
})->with(keyTypes());

it('eager loads links for several models of any key type', function (Closure $make): void {
    /** @var IntRecord|UlidRecord|UuidRecord $first */
    $first = $make();
    /** @var IntRecord|UlidRecord|UuidRecord $second */
    $second = $make();

    $first->publicLink('view')->issue();
    $first->publicLink('accept')->issue();
    $second->publicLink('view')->issue();

    $loaded = $first::query()->with('publicLinks')->orderBy('created_at')->get();

    expect($loaded->map(fn (Model $model): int => $model->getRelation('publicLinks')->count())->sort()->values()->all())
        ->toBe([1, 2]);
})->with(keyTypes());

it('generates a 43 character base64url token and never stores it in plain text', function (): void {
    $record = IntRecord::create(['name' => 'Quote']);

    $issued = $record->publicLink('view')->issue();
    $row = (array) DB::table(config('filament-public-links.tables.links'))->where('id', $issued->link->id)->first();

    expect($issued->token)->toMatch('/^[A-Za-z0-9_-]{43}$/')
        ->and($row['token_hash'])->toBe(hash('sha256', $issued->token))
        ->and(Crypt::decryptString((string) $row['token_encrypted']))->toBe($issued->token);

    foreach ($row as $column => $value) {
        expect(str_contains((string) $value, $issued->token))->toBeFalse("column {$column} holds the plain token");
    }
});

it('keeps the hash and the ciphertext out of arrays and JSON', function (): void {
    $issued = IntRecord::create(['name' => 'Quote'])->publicLink('view')->issue();

    $json = $issued->link->toJson();

    expect($issued->link->toArray())->not->toHaveKeys(['token_hash', 'token_encrypted'])
        ->and($json)->not->toContain($issued->token)
        ->and($json)->not->toContain($issued->link->token_hash);
});

it('never issues the same token twice', function (): void {
    $record = IntRecord::create(['name' => 'Quote']);

    $tokens = collect(range(1, 100))->map(fn (): string => $record->publicLink('view')->issue()->token);

    expect($tokens->unique())->toHaveCount(100);
});

it('returns no URL instead of failing when the app key changed', function (): void {
    $issued = IntRecord::create(['name' => 'Quote'])->publicLink('view')->issue();

    app()->instance('encrypter', new Encrypter(random_bytes(32), 'AES-256-CBC'));
    Crypt::clearResolvedInstance('encrypter');

    expect($issued->link->url())->toBeNull();
});

it('fires PublicLinkCreated without the plain token in its payload', function (): void {
    Event::fake([PublicLinkCreated::class]);

    $issued = IntRecord::create(['name' => 'Quote'])->publicLink('view')->issue();

    Event::assertDispatched(
        PublicLinkCreated::class,
        fn (PublicLinkCreated $event): bool => $event->link->is($issued->link)
            && ! str_contains(serialize($event), $issued->token),
    );
});

it('expires after the configured default', function (): void {
    Carbon::setTestNow('2026-10-20 12:00:00');
    config()->set('filament-public-links.defaults.ttl_days', 14);

    $issued = IntRecord::create(['name' => 'Quote'])->publicLink('view')->issue();

    expect($issued->link->expires_at?->toDateTimeString())->toBe('2026-11-03 12:00:00');
});

it('takes an explicit expiry, relative or absolute', function (): void {
    Carbon::setTestNow('2026-10-20 12:00:00');
    $record = IntRecord::create(['name' => 'Quote']);

    $relative = $record->publicLink('view')->expiresIn(days: 2, hours: 3)->issue();
    $absolute = $record->publicLink('view')->expiresAt(Carbon::parse('2026-12-24 18:00:00'))->issue();

    expect($relative->link->expires_at?->toDateTimeString())->toBe('2026-10-22 15:00:00')
        ->and($absolute->link->expires_at?->toDateTimeString())->toBe('2026-12-24 18:00:00');
});

it('refuses an expiry in the past or beyond max_ttl_days', function (): void {
    Carbon::setTestNow('2026-10-20 12:00:00');
    $record = IntRecord::create(['name' => 'Quote']);

    expect(fn () => $record->publicLink('view')->expiresAt(Carbon::parse('2026-10-20 11:59:59'))->issue())
        ->toThrow(InvalidPublicLink::class)
        ->and(fn () => $record->publicLink('view')->expiresIn(days: 366)->issue())
        ->toThrow(ExpiryTooFar::class)
        ->and(PublicLink::query()->count())->toBe(0);
});

it('allows any expiry when max_ttl_days is null', function (): void {
    config()->set('filament-public-links.defaults.max_ttl_days', null);

    $issued = IntRecord::create(['name' => 'Quote'])->publicLink('view')->expiresIn(days: 3650)->issue();

    expect($issued->link->expires_at?->isFuture())->toBeTrue();
});

it('issues permanent links only when allow_permanent is on', function (): void {
    $record = IntRecord::create(['name' => 'Quote']);

    expect(fn () => $record->publicLink('view')->permanent()->issue())->toThrow(PermanentLinksDisabled::class);

    config()->set('filament-public-links.defaults.allow_permanent', true);
    $issued = $record->publicLink('view')->permanent()->issue();

    expect($issued->link->expires_at)->toBeNull()
        ->and($issued->link->isUsable())->toBeTrue();
});

it('rejects an invalid purpose, usage limit or unsaved model', function (): void {
    $record = IntRecord::create(['name' => 'Quote']);

    expect(fn () => $record->publicLink('View Quote')->issue())->toThrow(InvalidPublicLink::class)
        ->and(fn () => $record->publicLink('')->issue())->toThrow(InvalidPublicLink::class)
        ->and(fn () => $record->publicLink(str_repeat('a', 65))->issue())->toThrow(InvalidPublicLink::class)
        ->and(fn () => $record->publicLink('view')->maxUses(0)->issue())->toThrow(InvalidPublicLink::class)
        ->and(fn () => (new IntRecord(['name' => 'Draft']))->publicLink('view')->issue())->toThrow(InvalidPublicLink::class)
        ->and(PublicLink::query()->count())->toBe(0);
});

it('stores label, usage limit and handler meta', function (): void {
    $issued = IntRecord::create(['name' => 'Quote'])->publicLink('accept')
        ->label('Sent to Anna')
        ->maxUses(3)
        ->meta(['recipient' => 'anna@example.test', 'round' => 2])
        ->issue();

    $link = $issued->link->fresh();

    expect($link?->label)->toBe('Sent to Anna')
        ->and($link?->max_uses)->toBe(3)
        ->and($link?->meta)->toEqual(['recipient' => 'anna@example.test', 'round' => 2]);
});

it('records the issuer: the authenticated user by default, or an explicit one', function (): void {
    $anna = User::create(['name' => 'Anna', 'email' => 'anna@example.test', 'password' => 'x']);
    $bob = User::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'x']);
    $record = IntRecord::create(['name' => 'Quote']);

    Auth::setUser($anna);
    $implicit = $record->publicLink('view')->issue()->link;
    $explicit = $record->publicLink('view')->createdBy($bob)->issue()->link;

    expect($implicit->created_by_id)->toBe((string) $anna->id)
        ->and($implicit->createdBy?->is($anna))->toBeTrue()
        ->and($explicit->createdBy?->is($bob))->toBeTrue();
});

it('refuses a token length below 32 bytes, at issue time and at boot', function (): void {
    config()->set('filament-public-links.tokens.bytes', 16);
    $record = IntRecord::create(['name' => 'Quote']);

    expect(fn () => $record->publicLink('view')->issue())->toThrow(InsecureTokenConfiguration::class)
        ->and(fn () => (new PublicLinksServiceProvider(app()))->packageBooted())->toThrow(InsecureTokenConfiguration::class)
        ->and(PublicLink::query()->count())->toBe(0);
});
