<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Filament\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Victorio\PublicLinks\Filament\Support\Authorizer;
use Victorio\PublicLinks\Filament\Support\IssueLinkForm;
use Victorio\PublicLinks\Filament\Support\OwnerRecord;
use Victorio\PublicLinks\Support\PackageView;

/**
 * "New public link" for any resource page or table, for those who do not want the relation
 * manager:
 *
 *     protected function getHeaderActions(): array
 *     {
 *         return [IssuePublicLinkAction::make()];
 *     }
 *
 * It asks for purpose, expiry and usage limit, issues the link for the page's (or the row's)
 * record, then shows the URL once with a copy button. Requires `update` on that record.
 */
final class IssuePublicLinkAction extends Action
{
    private const string LINK_ARGUMENT = 'link';

    private const string URL_CONTEXT_MARKER = 'filamentPublicLinksUrl';

    public static function getDefaultName(): string
    {
        return 'issuePublicLink';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(IssueLinkForm::label('actions.issue.label'))
            ->icon('heroicon-o-link')
            ->modalHeading(static fn (Action $action, mixed $livewire): string => IssueLinkForm::label(
                self::shownLinkKey($action, $livewire) !== null ? 'actions.url.heading' : 'actions.issue.heading',
            ))
            ->modalSubmitActionLabel(IssueLinkForm::label('actions.issue.submit'))
            ->modalSubmitAction(static fn (Action $action, mixed $livewire): ?bool => self::shownLinkKey($action, $livewire) !== null
                ? false
                : null)
            ->modalCancelActionLabel(static fn (Action $action, mixed $livewire): ?string => self::shownLinkKey($action, $livewire) !== null
                ? IssueLinkForm::label('actions.url.close')
                : null)
            ->schema(static function (Action $action, mixed $livewire, ?Model $record): array {
                $owner = OwnerRecord::resolve($livewire, $record);

                return self::shownLinkKey($action, $livewire) !== null || $owner === null
                    ? []
                    : IssueLinkForm::schema($owner);
            })
            ->modalContent(static function (Action $action, mixed $livewire, ?Model $record): ?View {
                $key = self::shownLinkKey($action, $livewire);

                if ($key === null) {
                    return null;
                }

                $owner = OwnerRecord::resolve($livewire, $record);
                $link = $owner === null ? null : OwnerRecord::link($owner, $key);

                return PackageView::make('filament.url', ['url' => $link?->url()]);
            })
            ->visible(static fn (mixed $livewire, ?Model $record): bool => Authorizer::allows(
                Authorizer::UPDATE,
                OwnerRecord::resolve($livewire, $record),
            ))
            ->authorize(static fn (mixed $livewire, ?Model $record): bool => Authorizer::allows(
                Authorizer::UPDATE,
                OwnerRecord::resolve($livewire, $record),
            ))
            ->action(function (array $data, mixed $livewire, ?Model $record): void {
                if (self::shownLinkKey($this, $livewire) !== null) {
                    return;
                }

                $owner = OwnerRecord::resolve($livewire, $record);

                if ($owner === null) {
                    return;
                }

                $issued = IssueLinkForm::issue($owner, $data);

                Notification::make()
                    ->success()
                    ->title(IssueLinkForm::label('actions.issue.created'))
                    ->send();

                // Re-open this action in the same place (page, table header or table row), now
                // showing the URL of the new link once. Filament treats a mounted action as
                // replaced only when its name or context changes, so the context gets a marker
                // (Filament ignores keys it does not know). Filament resets the action object's
                // arguments after the call, so the URL state is read from the mounted action
                // entry instead (shownLinkKey()).
                if (is_object($livewire) && method_exists($livewire, 'replaceMountedAction')) {
                    $livewire->replaceMountedAction(
                        $this->getName(),
                        [self::LINK_ARGUMENT => $issued->link->getKey()],
                        [...self::mountedContext($livewire), self::URL_CONTEXT_MARKER => true],
                    );
                }
            });
    }

    /**
     * The key of the link whose URL this action is showing, or null while it shows the form. Read
     * from the component's mounted actions: only an entry for this action, marked by this action.
     */
    private static function shownLinkKey(Action $action, mixed $livewire): ?string
    {
        if (! is_object($livewire) || ! property_exists($livewire, 'mountedActions') || ! is_array($livewire->mountedActions)) {
            return null;
        }

        $last = end($livewire->mountedActions);

        if (! is_array($last) || ($last['name'] ?? null) !== $action->getName()) {
            return null;
        }

        $context = $last['context'] ?? [];
        $arguments = $last['arguments'] ?? [];
        $key = is_array($arguments) ? ($arguments[self::LINK_ARGUMENT] ?? null) : null;

        return is_array($context) && ($context[self::URL_CONTEXT_MARKER] ?? false) === true && is_string($key)
            ? $key
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function mountedContext(object $livewire): array
    {
        $mounted = property_exists($livewire, 'mountedActions') ? $livewire->mountedActions : [];

        if (! is_array($mounted) || $mounted === []) {
            return [];
        }

        $last = end($mounted);
        $context = is_array($last) ? ($last['context'] ?? []) : [];

        return is_array($context) ? $context : [];
    }
}
