<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Tests\Fixtures\Filament\Pages;

use Filament\Resources\Pages\ListRecords;
use Victorio\PublicLinks\Tests\Fixtures\Filament\IntRecordResource;

final class ListIntRecords extends ListRecords
{
    protected static string $resource = IntRecordResource::class;
}
