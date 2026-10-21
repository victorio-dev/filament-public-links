<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Concerns;

use Illuminate\Database\Eloquent\Model;
use Victorio\PublicLinks\Builders\PendingPublicLink;
use Victorio\PublicLinks\Models\PublicLink;
use Victorio\PublicLinks\Support\PublicLinksConfig;
use Victorio\Support\Database\StringKeyedMorphMany;

/**
 * Gives any Eloquent model public links, whatever its key type (int, ULID or UUID).
 *
 *     use HasPublicLinks;
 *
 *     $issued = $quote->publicLink('view')->expiresIn(days: 14)->issue();
 *     $quote->publicLinks()->active()->forPurpose('view')->get();
 *     $quote->revokePublicLinks('view');
 *
 * @mixin Model
 */
trait HasPublicLinks
{
    /**
     * @return StringKeyedMorphMany<PublicLink, $this>
     */
    public function publicLinks(): StringKeyedMorphMany
    {
        $model = PublicLinksConfig::linkModel();
        $instance = new $model;
        $table = $instance->getTable();

        return new StringKeyedMorphMany(
            $instance->newQuery(),
            $this,
            $table.'.linkable_type',
            $table.'.linkable_id',
            $this->getKeyName(),
        );
    }

    /**
     * The language the Filament form proposes for this record's links (e.g. a quote's language).
     * Override it in the model; null proposes the app locale.
     */
    public function publicLinkLocale(): ?string
    {
        return null;
    }

    /**
     * Starts a new link for the given purpose; nothing is written until ->issue().
     */
    public function publicLink(string $purpose): PendingPublicLink
    {
        return new PendingPublicLink($this, $purpose);
    }

    public function hasActivePublicLink(?string $purpose = null): bool
    {
        $query = $this->publicLinks()->active();

        if ($purpose !== null) {
            $query->forPurpose($purpose);
        }

        return $query->exists();
    }

    /**
     * Revokes every active link of this model, or only those of one purpose. Returns how many
     * links this call revoked.
     */
    public function revokePublicLinks(?string $purpose = null, ?Model $by = null): int
    {
        $query = $this->publicLinks()->active();

        if ($purpose !== null) {
            $query->forPurpose($purpose);
        }

        return $query->get()
            ->filter(static fn (PublicLink $link): bool => $link->revoke($by))
            ->count();
    }
}
