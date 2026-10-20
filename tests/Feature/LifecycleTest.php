<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Victorio\PublicLinks\Enums\PublicLinkStatus;
use Victorio\PublicLinks\Events\PublicLinkExhausted;
use Victorio\PublicLinks\Events\PublicLinkRevoked;
use Victorio\PublicLinks\Models\PublicLink;
use Victorio\PublicLinks\Tests\Fixtures\IntRecord;
use Victorio\PublicLinks\Tests\Fixtures\User;

it('reports its status, revoked before expired before exhausted', function (): void {
    Carbon::setTestNow('2026-10-20 12:00:00');
    $link = IntRecord::create(['name' => 'Quote'])->publicLink('view')->expiresIn(days: 1)->maxUses(1)->issue()->link;

    expect($link->status())->toBe(PublicLinkStatus::Active)
        ->and($link->isUsable())->toBeTrue();

    $link->recordUse();
    expect($link->status())->toBe(PublicLinkStatus::Exhausted);

    Carbon::setTestNow('2026-10-21 12:00:01');
    expect($link->status())->toBe(PublicLinkStatus::Expired);

    $link->revoke();
    expect($link->status())->toBe(PublicLinkStatus::Revoked)
        ->and($link->isUsable())->toBeFalse()
        ->and($link->status()->label())->toBe('Revoked');
});

it('labels every status in English and Hungarian', function (): void {
    app()->setLocale('hu');

    expect(collect(PublicLinkStatus::cases())->map(fn (PublicLinkStatus $status): string => $status->label())->all())
        ->toBe(['Aktív', 'Lejárt', 'Visszavonva', 'Felhasználva']);
});

it('scopes active links in the database: not revoked, not expired, uses left', function (): void {
    Carbon::setTestNow('2026-10-20 12:00:00');
    $record = IntRecord::create(['name' => 'Quote']);

    $active = $record->publicLink('view')->issue()->link;
    $record->publicLink('view')->issue()->link->revoke();
    $record->publicLink('view')->expiresIn(hours: 1)->issue();
    $exhausted = $record->publicLink('view')->maxUses(1)->issue()->link;
    $exhausted->recordUse();
    $otherPurpose = $record->publicLink('accept')->issue()->link;

    Carbon::setTestNow('2026-10-20 13:00:01');

    expect($record->publicLinks()->active()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$active->id, $otherPurpose->id])->sort()->values()->all())
        ->and($record->publicLinks()->active()->forPurpose('view')->pluck('id')->all())->toBe([$active->id])
        ->and($record->hasActivePublicLink('view'))->toBeTrue()
        ->and($record->hasActivePublicLink('download'))->toBeFalse();
});

it('revokes once: idempotent, one event, records who revoked it', function (): void {
    Event::fake([PublicLinkRevoked::class]);
    $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'x']);
    $link = IntRecord::create(['name' => 'Quote'])->publicLink('view')->issue()->link;
    $sameLinkElsewhere = PublicLink::query()->findOrFail($link->id);

    expect($link->revoke($admin))->toBeTrue()
        ->and($link->revoke($admin))->toBeFalse()
        ->and($sameLinkElsewhere->revoke())->toBeFalse()
        ->and($sameLinkElsewhere->isRevoked())->toBeTrue();

    Event::assertDispatchedTimes(PublicLinkRevoked::class, 1);

    $fresh = $link->fresh();
    expect($fresh?->revoked_by_id)->toBe((string) $admin->id)
        ->and($fresh?->revokedBy?->is($admin))->toBeTrue();
});

it('revokes the active links of one record and purpose only', function (): void {
    $quote = IntRecord::create(['name' => 'Quote']);
    $other = IntRecord::create(['name' => 'Other quote']);

    $quote->publicLink('view')->issue();
    $quote->publicLink('view')->issue();
    $acceptLink = $quote->publicLink('accept')->issue()->link;
    $otherLink = $other->publicLink('view')->issue()->link;

    expect($quote->revokePublicLinks('view'))->toBe(2)
        ->and($quote->revokePublicLinks('view'))->toBe(0)
        ->and($acceptLink->fresh()?->isRevoked())->toBeFalse()
        ->and($otherLink->fresh()?->isRevoked())->toBeFalse()
        ->and($quote->revokePublicLinks())->toBe(1);
});

it('counts uses without a limit', function (): void {
    $link = IntRecord::create(['name' => 'Quote'])->publicLink('view')->issue()->link;

    expect($link->recordUse())->toBeTrue()
        ->and($link->recordUse())->toBeTrue()
        ->and($link->use_count)->toBe(2)
        ->and($link->fresh()?->use_count)->toBe(2);
});

it('stops at the usage limit and fires PublicLinkExhausted exactly once', function (): void {
    Event::fake([PublicLinkExhausted::class]);
    $link = IntRecord::create(['name' => 'Quote'])->publicLink('accept')->maxUses(2)->issue()->link;

    expect($link->recordUse())->toBeTrue()
        ->and($link->recordUse())->toBeTrue()
        ->and($link->recordUse())->toBeFalse()
        ->and($link->use_count)->toBe(2)
        ->and($link->isExhausted())->toBeTrue();

    Event::assertDispatchedTimes(PublicLinkExhausted::class, 1);
});

it('gives the last use to exactly one of two stale copies of the link', function (): void {
    Event::fake([PublicLinkExhausted::class]);
    $link = IntRecord::create(['name' => 'Quote'])->publicLink('accept')->maxUses(3)->issue()->link;

    // Two requests loaded the link while it still had one use left.
    PublicLink::query()->whereKey($link->id)->update(['use_count' => 2]);
    $first = PublicLink::query()->findOrFail($link->id);
    $second = PublicLink::query()->findOrFail($link->id);

    $results = [$first->recordUse(), $second->recordUse()];

    expect($results)->toBe([true, false])
        ->and($link->fresh()?->use_count)->toBe(3);

    Event::assertDispatchedTimes(PublicLinkExhausted::class, 1);
});

it('records no use on a revoked or expired link', function (): void {
    Carbon::setTestNow('2026-10-20 12:00:00');
    $record = IntRecord::create(['name' => 'Quote']);

    $revoked = $record->publicLink('view')->maxUses(5)->issue()->link;
    $revoked->revoke();
    $expired = $record->publicLink('view')->expiresIn(hours: 1)->issue()->link;

    Carbon::setTestNow('2026-10-20 13:00:01');

    expect($revoked->recordUse())->toBeFalse()
        ->and($expired->recordUse())->toBeFalse()
        ->and($revoked->fresh()?->use_count)->toBe(0)
        ->and($expired->fresh()?->use_count)->toBe(0);
});
