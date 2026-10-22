<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Builders;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Victorio\PublicLinks\Data\IssuedPublicLink;
use Victorio\PublicLinks\Events\PublicLinkCreated;
use Victorio\PublicLinks\Exceptions\ExpiryTooFar;
use Victorio\PublicLinks\Exceptions\InvalidPublicLink;
use Victorio\PublicLinks\Exceptions\PermanentLinksDisabled;
use Victorio\PublicLinks\Exceptions\UnknownPurpose;
use Victorio\PublicLinks\PublicLinkRegistry;
use Victorio\PublicLinks\Support\PublicLinksConfig;
use Victorio\PublicLinks\Support\PublicLinkUrl;
use Victorio\PublicLinks\Support\TokenFactory;
use Victorio\Support\Database\ModelKey;

/**
 * Fluent builder returned by HasPublicLinks::publicLink(). Nothing is written until issue().
 *
 *     $issued = $quote->publicLink('view')->expiresIn(days: 14)->maxUses(3)->issue();
 */
final class PendingPublicLink
{
    private const string PURPOSE_PATTERN = '/^[a-z0-9][a-z0-9._-]{0,63}$/';

    // A language tag as Laravel uses them: "en", "pt_BR", "zh-Hant"; at most 16 characters.
    private const string LOCALE_PATTERN = '/^[A-Za-z]{2,3}([_-][A-Za-z0-9]{2,8}){0,2}$/';

    private ?DateTimeInterface $expiresAt = null;

    private bool $permanent = false;

    private ?int $maxUses;

    private ?string $label = null;

    private ?string $locale = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $meta = null;

    private ?Model $createdBy = null;

    public function __construct(
        private readonly Model $linkable,
        private readonly string $purpose,
    ) {
        $this->maxUses = PublicLinksConfig::defaultMaxUses();
    }

    public function expiresIn(int $days = 0, int $hours = 0, int $minutes = 0): self
    {
        $this->expiresAt = CarbonImmutable::now()->addDays($days)->addHours($hours)->addMinutes($minutes);
        $this->permanent = false;

        return $this;
    }

    /**
     * The moment the link stops opening, in any timezone: it is converted to the app timezone
     * before it is stored, so the same moment is kept, not the same clock time.
     */
    public function expiresAt(DateTimeInterface $expiresAt): self
    {
        $this->expiresAt = CarbonImmutable::instance($expiresAt);
        $this->permanent = false;

        return $this;
    }

    /**
     * No expiry at all. Only allowed when defaults.allow_permanent is true.
     */
    public function permanent(): self
    {
        $this->permanent = true;
        $this->expiresAt = null;

        return $this;
    }

    public function maxUses(?int $uses): self
    {
        $this->maxUses = $uses;

        return $this;
    }

    /**
     * The language of the link's public pages (e.g. the quote's language). Null: the browser's
     * Accept-Language among the supported locales, then the app locale.
     */
    public function locale(?string $locale): self
    {
        $this->locale = $locale === null || trim($locale) === '' ? null : trim($locale);

        return $this;
    }

    public function label(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Handler-owned data stored with the link, e.g. the recipient. Never put the token here.
     *
     * @param  array<string, mixed>|null  $meta
     */
    public function meta(?array $meta): self
    {
        $this->meta = $meta;

        return $this;
    }

    /**
     * The issuer; defaults to the authenticated user.
     */
    public function createdBy(?Model $user): self
    {
        $this->createdBy = $user;

        return $this;
    }

    public function issue(): IssuedPublicLink
    {
        $this->validate();

        $token = TokenFactory::generate();
        $issuer = $this->createdBy ?? $this->authenticatedUser();

        $model = PublicLinksConfig::linkModel();
        $link = new $model([
            'purpose' => $this->purpose,
            'locale' => $this->locale,
            'token_hash' => TokenFactory::hash($token),
            'token_encrypted' => Crypt::encryptString($token),
            'label' => $this->label,
            'expires_at' => $this->resolveExpiry(),
            'max_uses' => $this->maxUses,
            'created_by_type' => $issuer?->getMorphClass(),
            'created_by_id' => $issuer === null ? null : ModelKey::of($issuer),
            'meta' => $this->meta,
        ]);

        // Set directly rather than through the relation, so any model can be linked and the id is
        // always the string form (StringKeyedMorphMany reads it back the same way).
        $link->forceFill([
            'linkable_type' => $this->linkable->getMorphClass(),
            'linkable_id' => ModelKey::of($this->linkable),
        ]);

        $tenant = $this->currentTenant();

        if ($tenant !== null) {
            $link->forceFill([PublicLinksConfig::tenantColumn() => ModelKey::of($tenant)]);
        }

        $link->save();

        event(new PublicLinkCreated($link));

        return new IssuedPublicLink(PublicLinkUrl::for($token), $token, $link);
    }

    private function validate(): void
    {
        if (! $this->linkable->exists) {
            throw InvalidPublicLink::unsavedModel();
        }

        if (preg_match(self::PURPOSE_PATTERN, $this->purpose) !== 1) {
            throw InvalidPublicLink::purpose($this->purpose);
        }

        $morphClass = $this->linkable->getMorphClass();

        if (! app(PublicLinkRegistry::class)->has($morphClass, $this->purpose)) {
            throw UnknownPurpose::for($morphClass, $this->purpose);
        }

        if ($this->locale !== null && preg_match(self::LOCALE_PATTERN, $this->locale) !== 1) {
            throw InvalidPublicLink::locale($this->locale);
        }

        if ($this->maxUses !== null && $this->maxUses < 1) {
            throw InvalidPublicLink::usageLimit($this->maxUses);
        }

        // Fail on a misconfigured token length before anything is written.
        TokenFactory::bytes();
    }

    private function resolveExpiry(): ?CarbonImmutable
    {
        if ($this->permanent) {
            if (! PublicLinksConfig::allowPermanent()) {
                throw PermanentLinksDisabled::make();
            }

            return null;
        }

        $now = CarbonImmutable::now();
        $expiresAt = $this->expiresAt === null
            ? $now->addDays(PublicLinksConfig::defaultTtlDays())
            : CarbonImmutable::instance($this->expiresAt);

        if ($expiresAt->lessThanOrEqualTo($now)) {
            throw InvalidPublicLink::expiryInThePast($expiresAt);
        }

        $maxTtlDays = PublicLinksConfig::maxTtlDays();

        if ($maxTtlDays !== null && $expiresAt->greaterThan($now->addDays($maxTtlDays))) {
            throw ExpiryTooFar::beyond($expiresAt, $maxTtlDays);
        }

        // Eloquent stores a date's clock time as it is, so it is converted to the app timezone first.
        return $expiresAt->setTimezone(self::appTimezone());
    }

    private static function appTimezone(): string
    {
        $timezone = config('app.timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : 'UTC';
    }

    private function currentTenant(): ?Model
    {
        if (! PublicLinksConfig::tenancyEnabled()) {
            return null;
        }

        return Filament::getTenant();
    }

    private function authenticatedUser(): ?Model
    {
        $user = auth()->user();

        return $user instanceof Model ? $user : null;
    }
}
