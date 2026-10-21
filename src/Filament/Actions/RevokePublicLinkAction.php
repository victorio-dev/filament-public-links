<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Filament\Actions;

use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Victorio\PublicLinks\Filament\RelationManagers\PublicLinksRelationManager;
use Victorio\PublicLinks\Filament\Support\Authorizer;
use Victorio\PublicLinks\Filament\Support\IssueLinkForm;
use Victorio\PublicLinks\Filament\Support\OwnerRecord;
use Victorio\PublicLinks\Models\PublicLink;

/**
 * Revokes one link after confirmation. Requires `update` on the owner record.
 *
 * Public API (row action of a PublicLink table): `make()`, Filament's modifiers and
 * `configureUsing()` work as on any action.
 */
final class RevokePublicLinkAction extends Action
{
    public static function getDefaultName(): string
    {
        return 'revokePublicLink';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(IssueLinkForm::label('actions.revoke.label'))
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(IssueLinkForm::label('actions.revoke.heading'))
            ->modalDescription(IssueLinkForm::label('actions.revoke.description'))
            ->successNotificationTitle(IssueLinkForm::label('actions.revoke.success'))
            ->visible(static fn (PublicLink $record, mixed $livewire): bool => ! $record->isRevoked()
                && Authorizer::allows(Authorizer::UPDATE, OwnerRecord::resolve($livewire, $record)))
            ->authorize(static fn (PublicLink $record, mixed $livewire): bool => Authorizer::allows(
                Authorizer::UPDATE,
                OwnerRecord::resolve($livewire, $record),
            ))
            ->action(function (PublicLink $record, mixed $livewire): void {
                $user = auth()->user();
                $record->revoke($user instanceof Model ? $user : null);

                if (is_object($livewire) && method_exists($livewire, 'dispatch')) {
                    $livewire->dispatch(PublicLinksRelationManager::LINKS_CHANGED);
                }

                $this->success();
            });
    }
}
