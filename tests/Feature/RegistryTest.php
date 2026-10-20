<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use Victorio\PublicLinks\Exceptions\DuplicateHandler;
use Victorio\PublicLinks\Exceptions\UnknownPurpose;
use Victorio\PublicLinks\Facades\PublicLinks;
use Victorio\PublicLinks\Models\PublicLink;
use Victorio\PublicLinks\Tests\Fixtures\Handlers\ShowRecord;
use Victorio\PublicLinks\Tests\Fixtures\IntRecord;
use Victorio\PublicLinks\Tests\Fixtures\Tenant;

it('refuses to issue a link for a model and purpose without a handler', function (): void {
    $record = IntRecord::create(['name' => 'Quote']);

    expect(fn () => $record->publicLink('download')->issue())->toThrow(UnknownPurpose::class)
        ->and(PublicLink::query()->count())->toBe(0);
});

it('refuses a second handler for the same model and purpose', function (): void {
    expect(fn () => PublicLinks::handle(IntRecord::class, 'view', ShowRecord::class))
        ->toThrow(DuplicateHandler::class);
});

it('refuses a handler class that does not implement the contract', function (): void {
    expect(fn () => PublicLinks::handle(Tenant::class, 'view', Tenant::class))
        ->toThrow(InvalidArgumentException::class);
});

it('lists the purposes registered for a model', function (): void {
    expect(PublicLinks::purposesFor(IntRecord::class))->toBe(['view', 'accept'])
        ->and(PublicLinks::purposesFor(Tenant::class))->toBe([])
        ->and(PublicLinks::has(IntRecord::class, 'view'))->toBeTrue()
        ->and(PublicLinks::has(IntRecord::class, 'download'))->toBeFalse();
});

it('keys handlers by morph class, so a morph map alias works', function (): void {
    Relation::morphMap(['tenant' => Tenant::class]);

    try {
        PublicLinks::handle(Tenant::class, 'view', ShowRecord::class);

        expect(PublicLinks::has('tenant', 'view'))->toBeTrue()
            ->and(PublicLinks::has(Tenant::class, 'view'))->toBeTrue()
            ->and(PublicLinks::handlerFor('tenant', 'view'))->toBeInstanceOf(ShowRecord::class);
    } finally {
        Relation::morphMap([], merge: false);
    }
});
