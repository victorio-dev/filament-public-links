<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Support;

use Victorio\PublicLinks\Models\PublicLink;

/**
 * Finds the link of a presented token. A hit and a miss do the same work: one SHA-256, one lookup
 * on the unique token_hash index, one constant-time comparison (against a fixed dummy hash on a
 * miss). Only the token decides; the request's Filament tenant, if any, plays no part.
 *
 * @internal
 */
final class LinkResolver
{
    private const string DUMMY_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    public function __construct(private readonly TokenComparer $comparer) {}

    public function resolve(string $token): ?PublicLink
    {
        $hash = TokenFactory::hash($token);
        $model = PublicLinksConfig::linkModel();

        $link = $model::query()->where('token_hash', $hash)->first();
        $matches = $this->comparer->equals($link === null ? self::DUMMY_HASH : $link->token_hash, $hash);

        return $link !== null && $matches ? $link : null;
    }
}
