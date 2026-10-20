<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Filament\Actions;

use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Victorio\PublicLinks\Filament\Support\Authorizer;
use Victorio\PublicLinks\Filament\Support\IssueLinkForm;
use Victorio\PublicLinks\Filament\Support\OwnerRecord;
use Victorio\PublicLinks\Models\PublicLink;

/**
 * Revokes the selected links after confirmation; already revoked ones are left as they are.
 * Requires `update` on the owner record.
 *
 * @internal
 */
final class RevokePublicLinksBulkAction extends BulkAction
{
    public static function getDefaultName(): string
    {
        return 'revokePublicLinks';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(IssueLinkForm::label('actions.revoke_bulk.label'))
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(IssueLinkForm::label('actions.revoke_bulk.heading'))
            ->modalDescription(IssueLinkForm::label('actions.revoke_bulk.description'))
            ->successNotificationTitle(IssueLinkForm::label('actions.revoke_bulk.success'))
            ->deselectRecordsAfterCompletion()
            ->visible(static fn (mixed $livewire): bool => Authorizer::allows(Authorizer::UPDATE, OwnerRecord::resolve($livewire)))
            ->authorize(static fn (mixed $livewire): bool => Authorizer::allows(Authorizer::UPDATE, OwnerRecord::resolve($livewire)))
            ->action(function (Collection $records): void {
                $user = auth()->user();

                foreach ($records as $record) {
                    if ($record instanceof PublicLink) {
                        $record->revoke($user instanceof Model ? $user : null);
                    }
                }

                $this->success();
            });
    }
}
