<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Models;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Victorio\PublicLinks\Enums\PublicLinkStatus;
use Victorio\PublicLinks\Events\PublicLinkExhausted;
use Victorio\PublicLinks\Events\PublicLinkRevoked;
use Victorio\PublicLinks\Support\PublicLinksConfig;
use Victorio\PublicLinks\Support\PublicLinkUrl;
use Victorio\Support\Database\ModelKey;

/**
 * A public link to one model, for one purpose.
 *
 * Extension point: swap it through `filament-public-links.models.link` with a subclass, which is
 * why the class is not final (rule D3). Keep the parent's behaviour; the package relies on it.
 *
 * @property string $id
 * @property string $linkable_type
 * @property string $linkable_id
 * @property string $purpose
 * @property string|null $locale
 * @property string $token_hash
 * @property string $token_encrypted
 * @property string|null $label
 * @property Carbon|null $expires_at
 * @property int|null $max_uses
 * @property int $use_count
 * @property int $open_count
 * @property Carbon|null $first_opened_at
 * @property Carbon|null $last_opened_at
 * @property Carbon|null $revoked_at
 * @property string|null $created_by_type
 * @property string|null $created_by_id
 * @property string|null $revoked_by_type
 * @property string|null $revoked_by_id
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $linkable
 */
class PublicLink extends Model
{
    use HasUlids;
    use MassPrunable;

    /**
     * Mass assignment is limited to what PendingPublicLink writes; counters, timestamps and the
     * revocation columns change only through the methods below.
     *
     * @var list<string>
     */
    protected $fillable = [
        'purpose',
        'locale',
        'token_hash',
        'token_encrypted',
        'label',
        'expires_at',
        'max_uses',
        'created_by_type',
        'created_by_id',
        'meta',
    ];

    /**
     * Neither the hash nor the ciphertext ever leaves the model in arrays or JSON (rule E5).
     *
     * @var list<string>
     */
    protected $hidden = [
        'token_hash',
        'token_encrypted',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'use_count' => 0,
        'open_count' => 0,
    ];

    public function getTable(): string
    {
        return PublicLinksConfig::linksTable();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'linkable_id' => 'string',
            'expires_at' => 'datetime',
            'max_uses' => 'integer',
            'use_count' => 'integer',
            'open_count' => 'integer',
            'first_opened_at' => 'datetime',
            'last_opened_at' => 'datetime',
            'revoked_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function createdBy(): MorphTo
    {
        return $this->morphTo('created_by');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function revokedBy(): MorphTo
    {
        return $this->morphTo('revoked_by');
    }

    /**
     * @return HasMany<PublicLinkAccess, $this>
     */
    public function accesses(): HasMany
    {
        return $this->hasMany(PublicLinksConfig::accessModel(), 'public_link_id');
    }

    /**
     * Links that can still be opened: not revoked, not expired, uses left.
     *
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query
            ->whereNull('revoked_at')
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('max_uses')->orWhereColumn('use_count', '<', 'max_uses');
            });
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForPurpose(Builder $query, string $purpose): void
    {
        $query->where('purpose', $purpose);
    }

    public function status(): PublicLinkStatus
    {
        return match (true) {
            $this->isRevoked() => PublicLinkStatus::Revoked,
            $this->isExpired() => PublicLinkStatus::Expired,
            $this->isExhausted() => PublicLinkStatus::Exhausted,
            default => PublicLinkStatus::Active,
        };
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->max_uses !== null && $this->use_count >= $this->max_uses;
    }

    public function isUsable(): bool
    {
        return $this->status() === PublicLinkStatus::Active;
    }

    /**
     * Revokes the link. Idempotent: only the call that actually revokes it returns true and fires
     * PublicLinkRevoked, even with concurrent callers.
     */
    public function revoke(?Model $by = null): bool
    {
        $attributes = [
            'revoked_at' => now(),
            'revoked_by_type' => $by?->getMorphClass(),
            'revoked_by_id' => $by === null ? null : ModelKey::of($by),
        ];

        $revoked = static::query()
            ->whereKey($this->getKey())
            ->whereNull('revoked_at')
            ->update($attributes);

        if ($revoked === 0) {
            $this->refresh();

            return false;
        }

        $this->forceFill($attributes)->syncOriginalAttributes(array_keys($attributes));

        event(new PublicLinkRevoked($this));

        return true;
    }

    /**
     * Records one use, atomically. Returns false when the link is revoked, expired or out of uses.
     *
     * Two conditional updates instead of read-then-write: the first takes any use but the last,
     * the second only the last one, so exactly one caller reaches the limit and fires
     * PublicLinkExhausted, however many requests race for it.
     */
    public function recordUse(): bool
    {
        $usable = fn (): Builder => static::query()
            ->whereKey($this->getKey())
            ->whereNull('revoked_at')
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });

        if ($this->max_uses === null) {
            $recorded = $usable()->whereNull('max_uses')->increment('use_count') > 0;
            $this->refreshUseCount();

            return $recorded;
        }

        // max_uses is fixed at issue time; binding the known value (and requiring it unchanged)
        // keeps both conditions plain bound comparisons.
        $limit = $this->max_uses;

        $notLast = $usable()
            ->where('max_uses', $limit)
            ->where('use_count', '<', $limit - 1)
            ->increment('use_count');

        if ($notLast > 0) {
            $this->refreshUseCount();

            return true;
        }

        $last = $usable()
            ->where('max_uses', $limit)
            ->where('use_count', $limit - 1)
            ->increment('use_count');

        $this->refreshUseCount();

        if ($last === 0) {
            return false;
        }

        event(new PublicLinkExhausted($this));

        return true;
    }

    /**
     * Counts a successful open: open_count, last_opened_at, and first_opened_at on the first one.
     * Returns true for exactly one caller, the first open, however many requests race.
     *
     * @internal Called by the public route.
     */
    public function markOpened(): bool
    {
        $now = now();

        static::query()->whereKey($this->getKey())->increment('open_count', 1, ['last_opened_at' => $now]);

        $first = static::query()
            ->whereKey($this->getKey())
            ->whereNull('first_opened_at')
            ->update(['first_opened_at' => $now]) === 1;

        $fresh = static::query()->whereKey($this->getKey())->first();

        if ($fresh !== null) {
            $this->forceFill($fresh->only(['open_count', 'first_opened_at', 'last_opened_at', 'updated_at']))
                ->syncOriginal();
        }

        return $first;
    }

    /**
     * The public URL, decrypted from the stored copy. Null when the copy cannot be decrypted,
     * e.g. after an APP_KEY rotation without previous_keys; the link itself still works.
     */
    public function url(): ?string
    {
        try {
            return PublicLinkUrl::for(Crypt::decryptString($this->token_encrypted));
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * Expired and revoked links older than defaults.prune_links_after_days; none when it is null.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $days = PublicLinksConfig::pruneLinksAfterDays();

        if ($days === null) {
            return static::query()->whereKey([]);
        }

        $cutoff = CarbonImmutable::now()->subDays($days);

        return static::query()->where(function (Builder $query) use ($cutoff): void {
            $query->where('revoked_at', '<', $cutoff)->orWhere('expires_at', '<', $cutoff);
        });
    }

    private function refreshUseCount(): void
    {
        $count = static::query()->whereKey($this->getKey())->value('use_count');

        $this->use_count = is_numeric($count) ? (int) $count : $this->use_count;
        $this->syncOriginalAttribute('use_count');
    }
}
