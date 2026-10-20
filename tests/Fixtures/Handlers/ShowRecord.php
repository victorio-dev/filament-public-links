<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Tests\Fixtures\Handlers;

use Symfony\Component\HttpFoundation\Response;
use Victorio\PublicLinks\Data\PublicLinkVisit;
use Victorio\PublicLinks\Handlers\PublicLinkHandlerBase;

/**
 * A "view" handler: every open is a use. Keeps the last visit so tests can inspect it.
 */
final class ShowRecord extends PublicLinkHandlerBase
{
    public static ?PublicLinkVisit $lastVisit = null;

    public static int $calls = 0;

    public function show(PublicLinkVisit $visit): Response
    {
        self::$lastVisit = $visit;
        self::$calls++;

        $name = $visit->linkable->getAttribute('name');

        return response('<h1>Record: '.e(is_string($name) ? $name : '').'</h1>');
    }
}
