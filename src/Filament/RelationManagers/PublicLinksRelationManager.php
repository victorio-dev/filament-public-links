<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Filament\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Victorio\PublicLinks\Enums\PublicLinkStatus;
use Victorio\PublicLinks\Filament\Actions\CopyPublicLinkAction;
use Victorio\PublicLinks\Filament\Actions\IssuePublicLinkAction;
use Victorio\PublicLinks\Filament\Actions\RevokePublicLinkAction;
use Victorio\PublicLinks\Filament\Actions\RevokePublicLinksBulkAction;
use Victorio\PublicLinks\Filament\Actions\ViewAccessLogAction;
use Victorio\PublicLinks\Filament\Support\Authorizer;
use Victorio\PublicLinks\Filament\Support\IssueLinkForm;
use Victorio\PublicLinks\Models\PublicLink;
use Victorio\PublicLinks\PublicLinksPlugin;
use Victorio\PublicLinks\Support\ModelKey;
use Victorio\PublicLinks\Support\PublicLinksConfig;

/**
 * The public links of a record, for any resource whose model uses HasPublicLinks:
 *
 *     public static function getRelations(): array
 *     {
 *         return [PublicLinksRelationManager::class];
 *     }
 *
 * Seeing the links and their access log needs `view` on the owner record; creating, copying and
 * revoking need `update`. With Filament tenancy only the current tenant's links are listed.
 */
final class PublicLinksRelationManager extends RelationManager
{
    protected static string $relationship = 'publicLinks';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return IssueLinkForm::label('relation_manager.title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Authorizer::allows(Authorizer::VIEW, $ownerRecord);
    }

    public function table(Table $table): Table
    {
        $plugin = PublicLinksPlugin::current();

        return $table
            ->recordTitle(static fn (PublicLink $record): string => $plugin->getPurposeLabel($record->purpose))
            ->modifyQueryUsing(static fn (Builder $query): Builder => self::scopeToTenant($query))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('purpose')
                    ->label(IssueLinkForm::label('columns.purpose'))
                    ->formatStateUsing(static fn (string $state): string => $plugin->getPurposeLabel($state)),
                TextColumn::make('status')
                    ->label(IssueLinkForm::label('columns.status'))
                    ->state(static fn (PublicLink $record): string => $record->status()->value)
                    ->formatStateUsing(static fn (string $state): string => PublicLinkStatus::from($state)->label())
                    ->badge()
                    ->color(static fn (string $state): string => match (PublicLinkStatus::from($state)) {
                        PublicLinkStatus::Active => 'success',
                        PublicLinkStatus::Expired => 'gray',
                        PublicLinkStatus::Revoked => 'danger',
                        PublicLinkStatus::Exhausted => 'warning',
                    }),
                TextColumn::make('expires_at')
                    ->label(IssueLinkForm::label('columns.expires_at'))
                    ->dateTime()
                    ->placeholder(IssueLinkForm::label('columns.never'))
                    ->sortable(),
                TextColumn::make('use_count')
                    ->label(IssueLinkForm::label('columns.uses'))
                    ->formatStateUsing(static fn (PublicLink $record): string => $record->use_count.' / '
                        .($record->max_uses ?? IssueLinkForm::label('columns.unlimited'))),
                TextColumn::make('open_count')
                    ->label(IssueLinkForm::label('columns.opens'))
                    ->numeric(),
                TextColumn::make('last_opened_at')
                    ->label(IssueLinkForm::label('columns.last_opened_at'))
                    ->since()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(IssueLinkForm::label('columns.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(IssueLinkForm::label('filters.status'))
                    ->options(array_combine(
                        array_map(static fn (PublicLinkStatus $status): string => $status->value, PublicLinkStatus::cases()),
                        array_map(static fn (PublicLinkStatus $status): string => $status->label(), PublicLinkStatus::cases()),
                    ))
                    ->query(static function (Builder $query, array $data): Builder {
                        $status = is_string($data['value'] ?? null) ? PublicLinkStatus::tryFrom($data['value']) : null;

                        return $status === null ? $query : self::whereStatus($query, $status);
                    }),
            ])
            ->emptyStateHeading(IssueLinkForm::label('relation_manager.empty'))
            ->headerActions([
                IssuePublicLinkAction::make(),
            ])
            ->recordActions([
                CopyPublicLinkAction::make(),
                ViewAccessLogAction::make(),
                RevokePublicLinkAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    RevokePublicLinksBulkAction::make(),
                ]),
            ]);
    }

    /**
     * With Filament tenancy on and a current tenant, only that tenant's links (rule D9).
     *
     * @param  Builder<PublicLink>  $query
     * @return Builder<PublicLink>
     */
    private static function scopeToTenant(Builder $query): Builder
    {
        $tenant = PublicLinksConfig::tenancyEnabled() ? Filament::getTenant() : null;

        if ($tenant === null) {
            return $query;
        }

        return $query->where(
            $query->qualifyColumn(PublicLinksConfig::tenantColumn()),
            ModelKey::of($tenant),
        );
    }

    /**
     * The same rules as PublicLink::status(), as a query.
     *
     * @param  Builder<PublicLink>  $query
     * @return Builder<PublicLink>
     */
    private static function whereStatus(Builder $query, PublicLinkStatus $status): Builder
    {
        $now = now();
        $notExpired = static function (Builder $query) use ($now): void {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', $now);
        };

        return match ($status) {
            PublicLinkStatus::Active => $query->whereNull('revoked_at')
                ->where($notExpired)
                ->where(static function (Builder $query): void {
                    $query->whereNull('max_uses')->orWhereColumn('use_count', '<', 'max_uses');
                }),
            PublicLinkStatus::Revoked => $query->whereNotNull('revoked_at'),
            PublicLinkStatus::Expired => $query->whereNull('revoked_at')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', $now),
            PublicLinkStatus::Exhausted => $query->whereNull('revoked_at')
                ->where($notExpired)
                ->whereNotNull('max_uses')
                ->whereColumn('use_count', '>=', 'max_uses'),
        };
    }
}
