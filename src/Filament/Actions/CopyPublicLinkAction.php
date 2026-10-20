<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Filament\Actions;

use Filament\Actions\Action;
use Illuminate\Contracts\View\View;
use Victorio\PublicLinks\Filament\Support\Authorizer;
use Victorio\PublicLinks\Filament\Support\IssueLinkForm;
use Victorio\PublicLinks\Filament\Support\OwnerRecord;
use Victorio\PublicLinks\Models\PublicLink;
use Victorio\PublicLinks\Support\PackageView;

/**
 * "Copy link" on a link row: decrypts the token only when clicked, server side, and shows the URL
 * with a copy button. Nothing of the token is rendered into the table. Requires `update`.
 *
 * @internal
 */
final class CopyPublicLinkAction extends Action
{
    public static function getDefaultName(): string
    {
        return 'copyPublicLink';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(IssueLinkForm::label('actions.copy.label'))
            ->icon('heroicon-o-clipboard-document')
            ->color('gray')
            ->modalHeading(IssueLinkForm::label('actions.url.heading'))
            ->modalContent(static fn (PublicLink $record): View => PackageView::make('filament.url',
                ['url' => $record->url()],
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(IssueLinkForm::label('actions.url.close'))
            ->visible(static fn (PublicLink $record, mixed $livewire): bool => ! $record->isRevoked()
                && Authorizer::allows(Authorizer::UPDATE, OwnerRecord::resolve($livewire, $record)))
            ->authorize(static fn (PublicLink $record, mixed $livewire): bool => Authorizer::allows(
                Authorizer::UPDATE,
                OwnerRecord::resolve($livewire, $record),
            ));
    }
}
