<?php

declare(strict_types=1);

use function Pest\Laravel\get;

use Victorio\PublicLinks\Tests\Fixtures\IntRecord;

it('serves and builds links under the configured route prefix, without any model id', function (): void {
    $record = IntRecord::create(['name' => 'Quote']);

    $issued = $record->publicLink('view')->issue();

    expect($issued->url)->toBe(url('share/'.$issued->token))
        ->and($issued->link->url())->toBe($issued->url)
        ->and($issued->url)->not->toContain($issued->link->id)
        ->and($issued->url)->not->toContain('/'.$record->id);

    get('/share/'.$issued->token)->assertOk()->assertSee('Record: Quote');
    get('/p/'.$issued->token)->assertNotFound();
});
