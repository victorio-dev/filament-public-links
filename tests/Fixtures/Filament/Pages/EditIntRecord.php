<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Tests\Fixtures\Filament\Pages;

use Filament\Resources\Pages\EditRecord;
use Victorio\PublicLinks\Filament\Actions\IssuePublicLinkAction;
use Victorio\PublicLinks\Tests\Fixtures\Filament\IntRecordResource;

final class EditIntRecord extends EditRecord
{
    protected static string $resource = IntRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [IssuePublicLinkAction::make()];
    }
}
