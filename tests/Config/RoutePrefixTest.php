<?php

declare(strict_types=1);

use function Pest\Laravel\get;

use Victorio\PublicLinks\Tests\Fixtures\IntRecord;

it('serves and builds links under the configured route prefix, without any model id', function (): void {
    $record = IntRecord::create(['name' => 'Quote']);

    $issued = $record->publicLink('view')->issue();

    expect($issued->url)->toBe(url('share/'.$issued->token))
        ->and($issued->link->url())->toBe($issued->url)
        // The path is the prefix and the token, nothing else: no link or model id (rule E2).
        ->and(parse_url($issued->url, PHP_URL_PATH))->toBe('/share/'.$issued->token);

    get('/share/'.$issued->token)->assertOk()->assertSee('Record: Quote');
    get('/p/'.$issued->token)->assertNotFound();
});
