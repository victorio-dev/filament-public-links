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
 * The last 50 access rows of a link in a slide-over: time, outcome, action, shortened user agent.
 * The IP hash is never shown (and the raw IP is never stored). Requires `view`.
 *
 * Public API (row action of a PublicLink table): `make()`, Filament's modifiers and
 * `configureUsing()` work as on any action.
 */
final class ViewAccessLogAction extends Action
{
    public const int LIMIT = 50;

    public static function getDefaultName(): string
    {
        return 'viewPublicLinkAccessLog';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(IssueLinkForm::label('actions.access_log.label'))
            ->icon('heroicon-o-clock')
            ->color('gray')
            ->slideOver()
            ->modalHeading(static fn (PublicLink $record): string => $record->label === null
                ? IssueLinkForm::label('actions.access_log.heading')
                : __('filament-public-links::filament-public-links.filament.actions.access_log.heading_with_note', ['note' => $record->label]))
            ->modalDescription(IssueLinkForm::label('actions.access_log.description'))
            ->modalContent(static fn (PublicLink $record): View => PackageView::make('filament.access-log',
                ['accesses' => $record->accesses()->orderByDesc('accessed_at')->orderByDesc('id')->limit(self::LIMIT)->get()],
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(IssueLinkForm::label('actions.url.close'))
            ->visible(static fn (PublicLink $record, mixed $livewire): bool => Authorizer::allows(
                Authorizer::VIEW,
                OwnerRecord::resolve($livewire, $record),
            ))
            ->authorize(static fn (PublicLink $record, mixed $livewire): bool => Authorizer::allows(
                Authorizer::VIEW,
                OwnerRecord::resolve($livewire, $record),
            ));
    }
}
