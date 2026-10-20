<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Victorio\PublicLinks\Support\PublicLinksConfig;

/**
 * One public request against a link: append-only, pruned after access_log.prune_after_days
 * (rules E4-E6). The IP is stored as an HMAC, never in plain text.
 *
 * Extension point: swap it through `filament-public-links.models.access` with a subclass, which is
 * why the class is not final (rule D3).
 *
 * @property int $id
 * @property string|null $public_link_id
 * @property string $outcome
 * @property string|null $action
 * @property string $ip_hash
 * @property string|null $user_agent
 * @property Carbon $accessed_at
 */
class PublicLinkAccess extends Model
{
    use MassPrunable;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_link_id',
        'outcome',
        'action',
        'ip_hash',
        'user_agent',
        'accessed_at',
    ];

    /**
     * The IP hash is pseudonymous personal data; keep it out of arrays and JSON (rule E5).
     *
     * @var list<string>
     */
    protected $hidden = [
        'ip_hash',
    ];

    public function getTable(): string
    {
        return PublicLinksConfig::accessesTable();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accessed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PublicLink, $this>
     */
    public function link(): BelongsTo
    {
        return $this->belongsTo(PublicLinksConfig::linkModel(), 'public_link_id');
    }

    /**
     * Rows older than access_log.prune_after_days (rule E6). The host app schedules model:prune.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $cutoff = CarbonImmutable::now()->subDays(PublicLinksConfig::pruneAccessesAfterDays());

        return static::query()->where('accessed_at', '<', $cutoff);
    }
}
