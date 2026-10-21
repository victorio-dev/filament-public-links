<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Tests\Fixtures\Filament\Pages;

use Closure;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Victorio\PublicLinks\Data\IssuedPublicLink;
use Victorio\PublicLinks\Filament\Actions\IssuePublicLinkAction;
use Victorio\PublicLinks\Models\PublicLink;
use Victorio\PublicLinks\Tests\Fixtures\Filament\IntRecordResource;
use Victorio\PublicLinks\Tests\Fixtures\UlidRecord;

final class EditIntRecord extends EditRecord
{
    protected static string $resource = IntRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            IssuePublicLinkAction::make(),
            // A link to another model than the record: a ULID record made from it.
            IssuePublicLinkAction::make('issueForCopy')
                ->modalHeading('Share a copy')
                // A translation key resolves to its text.
                ->urlModalHeading('filament-public-links::filament-public-links.filament.actions.copy.label')
                ->fields(['expiry', 'locale'])
                ->issueUsing(fn (Model $record, array $data, Closure $pending): IssuedPublicLink => $pending(
                    UlidRecord::create(['name' => 'Copy of '.$record->getAttribute('name')]),
                    'view',
                )->issue())
                ->linkOwnedUsing(fn (Model $record, PublicLink $link): bool => $link->linkable instanceof UlidRecord
                    && $link->linkable->name === 'Copy of '.$record->getAttribute('name')),
        ];
    }
}
