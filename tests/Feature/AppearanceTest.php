<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

use Victorio\PublicLinks\Filament\Actions\CopyPublicLinkAction;
use Victorio\PublicLinks\Filament\Actions\IssuePublicLinkAction;
use Victorio\PublicLinks\Filament\Actions\RevokePublicLinkAction;
use Victorio\PublicLinks\Filament\Actions\RevokePublicLinksBulkAction;
use Victorio\PublicLinks\Filament\Actions\ViewAccessLogAction;
use Victorio\PublicLinks\Filament\RelationManagers\PublicLinksRelationManager;
use Victorio\PublicLinks\PublicLinksPlugin;
use Victorio\PublicLinks\Tests\Fixtures\Filament\Pages\EditIntRecord;
use Victorio\PublicLinks\Tests\Fixtures\IntRecord;
use Victorio\PublicLinks\Tests\Fixtures\IntRecordPolicy;
use Victorio\PublicLinks\Tests\Fixtures\User;
use Victorio\Support\Testing\PseudoLocale;

/**
 * The plugin's appearance options for the links table: the row-action presets and the
 * modify*ActionsUsing() closures (docs/conventions.md, "Filament appearance").
 */
beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'x']));
});

function withPlugin(PublicLinksPlugin $plugin): void
{
    Filament::getPanel('admin')->plugin($plugin);
}

/**
 * The links table of a record, typed as mixed: Livewire's Testable is generic in Livewire 4 but
 * not in 3, and the suite runs on both.
 */
function appearanceTable(IntRecord $record): mixed
{
    return Livewire::test(PublicLinksRelationManager::class, ['ownerRecord' => $record, 'pageClass' => EditIntRecord::class]);
}

/**
 * @return list<Action|ActionGroup>
 */
function rowActionsOf(mixed $component): array
{
    return array_values($component->instance()->getTable()->getRecordActions());
}

/**
 * The actions inside a group.
 *
 * @return array<Action|ActionGroup>
 */
function groupedActions(Action|ActionGroup $group): array
{
    expect($group)->toBeInstanceOf(ActionGroup::class);

    return $group instanceof ActionGroup ? $group->getActions() : [];
}

/**
 * @param  array<Action|ActionGroup>  $actions
 * @return list<string|null>
 */
function actionNames(array $actions): array
{
    return array_values(array_map(static fn (Action|ActionGroup $action): ?string => $action instanceof Action ? $action->getName() : null, $actions));
}

describe('row action presets', function (): void {
    it('shows labelled actions by default, like Filament', function (): void {
        $actions = rowActionsOf(appearanceTable(IntRecord::create(['name' => 'Quote'])));

        expect(actionNames($actions))->toBe(['copyPublicLink', 'viewPublicLinkAccessLog', 'revokePublicLink'])
            ->and(array_map(static fn (Action|ActionGroup $action): bool => $action->isIconButton(), $actions))->toBe([false, false, false]);
    });

    it('shows icon buttons with the label as tooltip', function (): void {
        withPlugin(PublicLinksPlugin::make()->rowActions('icons'));

        $actions = rowActionsOf(appearanceTable(IntRecord::create(['name' => 'Quote'])));

        expect(array_map(static fn (Action|ActionGroup $action): bool => $action->isIconButton(), $actions))->toBe([true, true, true])
            ->and(array_map(static fn (Action|ActionGroup $action): mixed => $action instanceof Action ? $action->getTooltip() : null, $actions))
            ->toBe(['Copy link', 'Access log', 'Revoke']);
    });

    it('collects them in one group', function (): void {
        withPlugin(PublicLinksPlugin::make()->rowActions('group'));

        $actions = rowActionsOf(appearanceTable(IntRecord::create(['name' => 'Quote'])));

        expect($actions)->toHaveCount(1)
            ->and(actionNames(groupedActions($actions[0])))->toBe(['copyPublicLink', 'viewPublicLinkAccessLog', 'revokePublicLink']);
    });

    it('refuses an unknown preset', function (): void {
        expect(fn (): PublicLinksPlugin => PublicLinksPlugin::make()->rowActions('buttons'))
            ->toThrow(InvalidArgumentException::class, 'labels", "icons", "group');
    });
});

describe('modify*ActionsUsing()', function (): void {
    it('reorders, omits and changes row actions, before the group wraps them', function (): void {
        withPlugin(PublicLinksPlugin::make()->rowActions('group')->modifyRowActionsUsing(
            fn (array $actions): array => [$actions['revokePublicLink'], $actions['copyPublicLink']->label('Grab')],
        ));
        $record = IntRecord::create(['name' => 'Quote']);
        $link = $record->publicLink('view')->issue()->link;

        $table = appearanceTable($record);
        $group = rowActionsOf($table)[0];

        expect(actionNames(groupedActions($group)))->toBe(['revokePublicLink', 'copyPublicLink']);
        $table->assertTableActionHasLabel('copyPublicLink', 'Grab', $link)
            ->assertTableActionDoesNotExist('viewPublicLinkAccessLog');
    });

    it('adds an own row action', function (): void {
        withPlugin(PublicLinksPlugin::make()->modifyRowActionsUsing(
            fn (array $actions): array => [...$actions, Action::make('ping')->label('Ping')],
        ));
        $record = IntRecord::create(['name' => 'Quote']);
        $link = $record->publicLink('view')->issue()->link;

        appearanceTable($record)->assertTableActionVisible('ping', $link)->assertTableActionHasLabel('ping', 'Ping', $link);
    });

    it('changes the header actions', function (): void {
        withPlugin(PublicLinksPlugin::make()->modifyHeaderActionsUsing(
            fn (array $actions): array => [$actions['issuePublicLink']->label('Share'), Action::make('help')],
        ));

        appearanceTable(IntRecord::create(['name' => 'Quote']))
            ->assertTableActionHasLabel('issuePublicLink', 'Share')
            ->assertTableActionExists('help');
    });

    it('changes the bulk actions, and drops the menu when none is left', function (): void {
        withPlugin(PublicLinksPlugin::make()->modifyToolbarActionsUsing(
            fn (array $actions): array => [...$actions, BulkAction::make('archive')],
        ));
        $toolbar = array_values(appearanceTable(IntRecord::create(['name' => 'Quote']))->instance()->getTable()->getToolbarActions());

        expect($toolbar)->toHaveCount(1)
            ->and($toolbar[0])->toBeInstanceOf(BulkActionGroup::class)
            ->and(actionNames(groupedActions($toolbar[0])))->toBe(['revokePublicLinks', 'archive']);

        withPlugin(PublicLinksPlugin::make()->modifyToolbarActionsUsing(fn (array $actions): array => []));

        expect(appearanceTable(IntRecord::create(['name' => 'Quote']))->instance()->getTable()->getToolbarActions())->toBe([]);
    });

    it('refuses a closure that returns something else than actions', function (): void {
        withPlugin(PublicLinksPlugin::make()->modifyRowActionsUsing(
            // Not an action; read from config, so static analysis lets the mistake through.
            fn (array $actions): array => (array) config('filament-public-links.not_an_action', ['copy']),
        ));

        expect(fn (): mixed => appearanceTable(IntRecord::create(['name' => 'Quote'])))
            // Thrown while the table renders, so Laravel wraps it in a view exception.
            ->toThrow(Exception::class, 'must return only actions or action groups');
    });
});

it('lets configureUsing() change every action of the package', function (): void {
    $record = IntRecord::create(['name' => 'Quote']);
    $link = $record->publicLink('view')->issue()->link;
    $rename = fn (string $label): Closure => fn (Action $action): Action => $action->label($label);

    CopyPublicLinkAction::configureUsing($rename('C'), during: fn (): mixed => ViewAccessLogAction::configureUsing($rename('L'), during: fn (): mixed => RevokePublicLinkAction::configureUsing($rename('R'), during: fn (): mixed => IssuePublicLinkAction::configureUsing($rename('I'), during: fn (): mixed => RevokePublicLinksBulkAction::configureUsing($rename('B'), during: fn (): mixed => appearanceTable($record)
        ->assertTableActionHasLabel('copyPublicLink', 'C', $link)
        ->assertTableActionHasLabel('viewPublicLinkAccessLog', 'L', $link)
        ->assertTableActionHasLabel('revokePublicLink', 'R', $link)
        ->assertTableActionHasLabel('issuePublicLink', 'I')
        ->assertTableBulkActionHasLabel('revokePublicLinks', 'B'))))));
});

dataset('compact presets', ['icons', 'group']);

it('keeps the authorization in every preset', function (string $preset): void {
    withPlugin(PublicLinksPlugin::make()->rowActions($preset));
    Gate::policy(IntRecord::class, IntRecordPolicy::class);
    $record = IntRecord::create(['name' => 'Quote']);
    $link = $record->publicLink('view')->issue()->link;
    actingAs(User::create(['name' => 'viewer', 'email' => 'viewer@example.test', 'password' => 'x']));

    appearanceTable($record)
        ->assertTableActionHidden('copyPublicLink', $link)
        ->assertTableActionHidden('revokePublicLink', $link)
        ->assertTableActionVisible('viewPublicLinkAccessLog', $link);

    // A hand-crafted request for a hidden action is refused as well.
    appearanceTable($record)
        ->call('mountAction', 'revokePublicLink', [], ['table' => true, 'recordKey' => $link->getKey()])
        ->call('callMountedAction');

    expect($link->fresh()?->isRevoked())->toBeFalse();
})->with('compact presets');

it('has no hard-coded text in the compact presets', function (string $preset): void {
    withPlugin(PublicLinksPlugin::make()->rowActions($preset));
    $record = IntRecord::create(['name' => 'Quote']);
    $record->publicLink('view')->issue();
    PseudoLocale::enable();

    $html = appearanceTable($record)->html();

    expect(PseudoLocale::untranslated($html, [(string) config('app.name'), 'Quote', Carbon\CarbonImmutable::now()->diffForHumans()]))->toBe([])
        ->and($html)->toContain('⟦');
})->with('compact presets');
