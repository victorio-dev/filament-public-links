<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Filament\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use UnexpectedValueException;
use Victorio\PublicLinks\Builders\PendingPublicLink;
use Victorio\PublicLinks\Data\IssuedPublicLink;
use Victorio\PublicLinks\Filament\RelationManagers\PublicLinksRelationManager;
use Victorio\PublicLinks\Filament\Support\Authorizer;
use Victorio\PublicLinks\Filament\Support\IssueLinkForm;
use Victorio\PublicLinks\Filament\Support\OwnerRecord;
use Victorio\PublicLinks\Models\PublicLink;
use Victorio\PublicLinks\Support\PackageView;
use Victorio\PublicLinks\Support\PublicLinksConfig;

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
 *
 * A link to another model than the record (e.g. a sealed version of it) — the form, the URL
 * modal and the authorization stay the same:
 *
 *     IssuePublicLinkAction::make('sendForApproval')
 *         ->fields(['expiry', 'locale'])
 *         ->issueUsing(fn (Model $record, array $data, Closure $pending): IssuedPublicLink =>
 *             $pending($record->seal(...), 'approval')->issue())
 *         ->linkOwnedUsing(fn (Model $record, PublicLink $link): bool => …);
 */
final class IssuePublicLinkAction extends Action
{
    private const string LINK_ARGUMENT = 'link';

    private const string URL_CONTEXT_MARKER = 'filamentPublicLinksUrl';

    /**
     * @var list<string>|null
     */
    private ?array $fields = null;

    /**
     * @var (Closure(Model, array<string, mixed>, Closure(Model, string): PendingPublicLink): IssuedPublicLink)|null
     */
    private ?Closure $issueUsing = null;

    /**
     * @var (Closure(Model, PublicLink): bool)|null
     */
    private ?Closure $linkOwnedUsing = null;

    private string|Htmlable|null $formHeading = null;

    private ?string $urlHeading = null;

    /**
     * The heading while the form is shown (default "New public link"): a text or a translation
     * key. The heading of the URL step is urlModalHeading(). A closure is not supported here,
     * because the action switches between the two headings itself.
     *
     * The parameter keeps Filament's signature.
     *
     * @param  string|Htmlable|(Closure(): mixed)|null  $heading
     */
    public function modalHeading(string|Htmlable|Closure|null $heading = null): static
    {
        $this->formHeading = $heading instanceof Closure ? null : $heading;

        return $this;
    }

    /**
     * The heading while the new link's URL is shown (default "Public link"): a text or a
     * translation key.
     */
    public function urlModalHeading(?string $heading): static
    {
        $this->urlHeading = $heading;

        return $this;
    }

    /**
     * The form fields to show, from `purpose`, `expiry`, `max_uses`, `locale`, `label`; the rest
     * are left out (and their defaults apply). Default: all.
     *
     * @param  list<string>  $fields
     */
    public function fields(array $fields): static
    {
        $this->fields = array_values(array_intersect($fields, IssueLinkForm::FIELDS));

        return $this;
    }

    /**
     * Issues the link yourself, e.g. for another model than the record. The callback receives the
     * record, the form data and `$pending(Model $linkable, string $purpose): PendingPublicLink`,
     * already configured from the form (expiry, usage limit, language, note, issuer), and returns
     * the issued link. Pair it with linkOwnedUsing(), so the URL modal can find the link.
     *
     * @param  Closure(Model, array<string, mixed>, Closure(Model, string): PendingPublicLink): IssuedPublicLink  $callback
     */
    public function issueUsing(Closure $callback): static
    {
        $this->issueUsing = $callback;

        return $this;
    }

    /**
     * Whether a link belongs to the record, for links that are not the record's own (see
     * issueUsing()). The URL modal shows a link only when this says yes: a mounted action's
     * arguments come from the client.
     *
     * @param  Closure(Model, PublicLink): bool  $callback
     */
    public function linkOwnedUsing(Closure $callback): static
    {
        $this->linkOwnedUsing = $callback;

        return $this;
    }

    public static function getDefaultName(): string
    {
        return 'issuePublicLink';
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Through the parent, so the form and URL headings set with modalHeading() and
        // urlModalHeading() keep this two-state heading.
        parent::modalHeading(fn (Action $action, mixed $livewire): string => self::shownLinkKey($action, $livewire) !== null
            ? self::text($this->urlHeading) ?? IssueLinkForm::label('actions.url.heading')
            : self::text($this->formHeading) ?? IssueLinkForm::label('actions.issue.heading'));

        $this
            ->label(IssueLinkForm::label('actions.issue.label'))
            ->icon('heroicon-o-link')
            ->modalSubmitActionLabel(IssueLinkForm::label('actions.issue.submit'))
            // Filament passes the submit button as `$action` here; see design.md "Filament internals" #4.
            ->modalSubmitAction(fn (mixed $livewire): ?bool => self::shownLinkKey($this, $livewire) !== null
                ? false
                : null)
            ->modalCancelActionLabel(static fn (Action $action, mixed $livewire): ?string => self::shownLinkKey($action, $livewire) !== null
                ? IssueLinkForm::label('actions.url.close')
                : null)
            ->schema(function (Action $action, mixed $livewire, ?Model $record): array {
                $owner = OwnerRecord::resolve($livewire, $record);

                return self::shownLinkKey($action, $livewire) !== null || $owner === null
                    ? []
                    : IssueLinkForm::schema($owner, $this->fields);
            })
            ->modalContent(function (Action $action, mixed $livewire, ?Model $record): ?View {
                $key = self::shownLinkKey($action, $livewire);

                if ($key === null) {
                    return null;
                }

                $owner = OwnerRecord::resolve($livewire, $record);

                return PackageView::make('filament.url', ['link' => $owner === null ? null : $this->ownedLink($owner, $key)]);
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

                $issued = $this->issueUsing === null
                    ? IssueLinkForm::issue($owner, $data)
                    : ($this->issueUsing)(
                        $owner,
                        $data,
                        static fn (Model $linkable, string $purpose): PendingPublicLink => IssueLinkForm::pending($linkable, $purpose, $data),
                    );

                if (! $issued instanceof IssuedPublicLink) {
                    throw new UnexpectedValueException('IssuePublicLinkAction::issueUsing() must return an IssuedPublicLink.');
                }

                if (is_object($livewire) && method_exists($livewire, 'dispatch')) {
                    $livewire->dispatch(PublicLinksRelationManager::LINKS_CHANGED);
                }

                Notification::make()
                    ->success()
                    ->title(IssueLinkForm::label('actions.issue.created'))
                    ->send();

                // Re-open this action in the same place (page, table header or table row), now
                // showing the URL of the new link once. Filament treats a mounted action as
                // replaced only when its name or context changes, so the context gets a marker
                // (Filament ignores keys it does not know). Filament resets the action object's
                // arguments after the call, so the URL state is read from the mounted action
                // entry instead (shownLinkKey()). See design.md "Filament internals" #1-#3.
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
     * A heading given as a translation key resolves to its translation; any other text stays.
     */
    private static function text(string|Htmlable|null $heading): ?string
    {
        if ($heading instanceof Htmlable) {
            return $heading->toHtml();
        }

        if ($heading === null || $heading === '') {
            return null;
        }

        $translated = __($heading);

        return is_string($translated) ? $translated : $heading;
    }

    /**
     * The link with that key when it belongs to the owner: one of its own links, or one the
     * linkOwnedUsing() callback accepts.
     */
    private function ownedLink(Model $owner, string $key): ?PublicLink
    {
        if ($this->linkOwnedUsing === null) {
            return OwnerRecord::link($owner, $key);
        }

        $model = PublicLinksConfig::linkModel();
        $link = $model::query()->whereKey($key)->first();

        return $link instanceof PublicLink && ($this->linkOwnedUsing)($owner, $link) === true ? $link : null;
    }

    /**
     * The key of the link whose URL this action is showing, or null while it shows the form. Read
     * from the component's mounted actions: only an entry for this action, marked by this action.
     * Relies on the entry shape; see design.md "Filament internals" #1.
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
