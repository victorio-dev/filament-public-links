<?php

declare(strict_types=1);

namespace Victorio\PublicLinks;

use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use InvalidArgumentException;

/**
 * Optional panel plugin. The relation manager and IssuePublicLinkAction work without it; register
 * it only to change these defaults:
 *
 *     PublicLinksPlugin::make()
 *         ->expiryOptions([1, 7, 30])
 *         ->defaultExpiry(7)
 *         ->purposeLabels(['view' => 'View quote', 'accept' => 'Accept quote'])
 */
final class PublicLinksPlugin implements Plugin
{
    /**
     * @var list<int>
     */
    private array $expiryOptions = [1, 7, 14, 30, 90];

    private int $defaultExpiry = 7;

    /**
     * @var array<string, string>
     */
    private array $purposeLabels = [];

    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(self::class)->getId());

        return $plugin;
    }

    /**
     * The plugin of the current panel, or one with the defaults when it is not registered.
     *
     * @internal
     */
    public static function current(): self
    {
        $panel = Filament::getCurrentPanel();
        $id = (new self)->getId();

        if ($panel !== null && $panel->hasPlugin($id)) {
            $plugin = $panel->getPlugin($id);

            if ($plugin instanceof self) {
                return $plugin;
            }
        }

        return new self;
    }

    public function getId(): string
    {
        return 'filament-public-links';
    }

    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void {}

    /**
     * The expiry choices of the "new link" form, in days. Choices beyond
     * defaults.max_ttl_days are not offered.
     *
     * @param  list<int>  $days
     */
    public function expiryOptions(array $days): self
    {
        $days = array_values(array_unique(array_filter($days, static fn (int $day): bool => $day > 0)));
        sort($days);

        if ($days === []) {
            throw new InvalidArgumentException('expiryOptions() needs at least one positive number of days.');
        }

        $this->expiryOptions = $days;

        return $this;
    }

    /**
     * @return list<int>
     */
    public function getExpiryOptions(): array
    {
        return $this->expiryOptions;
    }

    /**
     * The preselected expiry in days; it should be one of the expiry options.
     */
    public function defaultExpiry(int $days): self
    {
        $this->defaultExpiry = $days;

        return $this;
    }

    public function getDefaultExpiry(): int
    {
        return $this->defaultExpiry;
    }

    /**
     * Human labels for purposes, shown in the form and the table instead of the raw name.
     *
     * @param  array<string, string>  $labels
     */
    public function purposeLabels(array $labels): self
    {
        $this->purposeLabels = $labels;

        return $this;
    }

    public function getPurposeLabel(string $purpose): string
    {
        return $this->purposeLabels[$purpose] ?? $purpose;
    }
}
