<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Filament\Support;

use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Locale;
use Victorio\PublicLinks\Data\IssuedPublicLink;
use Victorio\PublicLinks\PublicLinkRegistry;
use Victorio\PublicLinks\PublicLinksPlugin;
use Victorio\PublicLinks\Support\PublicLinksConfig;

/**
 * The "new public link" form and what submitting it does; shared by the relation manager and
 * IssuePublicLinkAction.
 *
 * @internal
 */
final class IssueLinkForm
{
    public const string CUSTOM_EXPIRY = 'custom';

    /**
     * @return array<int, Component|\Filament\Forms\Components\Field>
     */
    public static function schema(Model $owner): array
    {
        $plugin = PublicLinksPlugin::current();
        $purposes = app(PublicLinkRegistry::class)->purposesFor($owner->getMorphClass());
        $maxTtlDays = PublicLinksConfig::maxTtlDays();
        $expiryOptions = self::expiryOptions($plugin->getExpiryOptions(), $maxTtlDays);
        $defaultExpiry = array_key_exists((string) $plugin->getDefaultExpiry(), $expiryOptions)
            ? (string) $plugin->getDefaultExpiry()
            : (string) array_key_first($expiryOptions);
        $notPermanent = static fn (Get $get): bool => ! $get('permanent');

        $purposeField = count($purposes) === 1
            ? Hidden::make('purpose')->default($purposes[0])
            : Select::make('purpose')
                ->label(self::label('form.purpose'))
                ->options(array_combine($purposes, array_map(
                    static fn (string $purpose): string => PurposeLabel::for($owner->getMorphClass(), $purpose),
                    $purposes,
                )))
                ->default($purposes[0] ?? null)
                ->required();

        // "Today" for the date picker is today where the admin is (Filament's display timezone).
        $today = CarbonImmutable::today(FilamentTimezone::get());

        return [
            $purposeField,
            Toggle::make('permanent')
                ->label(self::label('form.permanent'))
                ->visible(PublicLinksConfig::allowPermanent())
                ->live(),
            Select::make('expiry')
                ->label(self::label('form.expiry'))
                ->options($expiryOptions)
                ->default($defaultExpiry)
                ->required($notPermanent)
                ->visible($notPermanent)
                ->live(),
            DatePicker::make('expires_on')
                ->label(self::label('form.expires_on'))
                ->minDate($today->addDay())
                ->maxDate($maxTtlDays === null ? null : $today->addDays($maxTtlDays))
                ->rules(array_values(array_filter([
                    'after_or_equal:'.$today->addDay()->toDateString(),
                    $maxTtlDays === null ? null : 'before_or_equal:'.$today->addDays($maxTtlDays)->toDateString(),
                ])))
                ->required(static fn (Get $get): bool => ! $get('permanent') && $get('expiry') === self::CUSTOM_EXPIRY)
                ->visible(static fn (Get $get): bool => ! $get('permanent') && $get('expiry') === self::CUSTOM_EXPIRY),
            TextInput::make('max_uses')
                ->label(self::label('form.max_uses'))
                ->helperText(self::label('form.max_uses_help'))
                ->integer()
                ->minValue(1)
                ->nullable(),
            Select::make('locale')
                ->label(self::label('form.locale'))
                ->helperText(self::label('form.locale_help'))
                ->options(self::localeOptions())
                ->default(self::defaultLocale($owner))
                ->placeholder(self::label('form.locale_automatic'))
                ->nullable(),
            TextInput::make('label')
                ->label(self::label('form.label'))
                ->helperText(self::label('form.label_help'))
                ->maxLength(255)
                ->nullable(),
        ];
    }

    /**
     * Issues the link from the submitted form data, for the current user.
     *
     * @param  array<string, mixed>  $data
     */
    public static function issue(Model $owner, array $data): IssuedPublicLink
    {
        if (! method_exists($owner, 'publicLink')) {
            throw new InvalidArgumentException(sprintf('%s does not use the HasPublicLinks trait.', $owner::class));
        }

        $purpose = is_string($data['purpose'] ?? null) ? $data['purpose'] : '';
        $pending = $owner->publicLink($purpose);

        if (($data['permanent'] ?? false) === true) {
            $pending->permanent();
        } elseif (($data['expiry'] ?? null) === self::CUSTOM_EXPIRY && is_string($data['expires_on'] ?? null)) {
            // Valid through the chosen day in Filament's display timezone (stored in the app
            // timezone), but never past max_ttl_days from now.
            $expiresAt = CarbonImmutable::parse($data['expires_on'], FilamentTimezone::get())
                ->endOfDay()
                ->setTimezone(self::appTimezone());
            $maxTtlDays = PublicLinksConfig::maxTtlDays();

            if ($maxTtlDays !== null && $expiresAt->greaterThan(CarbonImmutable::now()->addDays($maxTtlDays))) {
                $expiresAt = CarbonImmutable::now()->addDays($maxTtlDays);
            }

            $pending->expiresAt($expiresAt);
        } elseif (is_numeric($data['expiry'] ?? null)) {
            $pending->expiresIn(days: (int) $data['expiry']);
        }

        $maxUses = $data['max_uses'] ?? null;
        $label = $data['label'] ?? null;
        $locale = $data['locale'] ?? null;

        return $pending
            ->locale(is_string($locale) && in_array($locale, PublicLinksConfig::supportedLocales(), true) ? $locale : null)
            ->maxUses(is_numeric($maxUses) ? (int) $maxUses : null)
            ->label(is_string($label) && trim($label) !== '' ? trim($label) : null)
            ->createdBy(self::user())
            ->issue();
    }

    /**
     * Keys are the day counts (PHP turns numeric string keys into ints) and "custom".
     *
     * @param  list<int>  $days
     * @return array<int|string, string>
     */
    private static function expiryOptions(array $days, ?int $maxTtlDays): array
    {
        $options = [];

        foreach ($days as $day) {
            if ($maxTtlDays === null || $day <= $maxTtlDays) {
                $options[(string) $day] = trans_choice('filament-public-links::filament-public-links.filament.form.expiry_days', $day, ['count' => $day]);
            }
        }

        $options[self::CUSTOM_EXPIRY] = self::label('form.expiry_custom');

        return $options;
    }

    /**
     * The supported locales, each named in its own language ("Deutsch", "Magyar") by intl.
     *
     * @return array<string, string>
     */
    private static function localeOptions(): array
    {
        $options = [];

        foreach (PublicLinksConfig::supportedLocales() as $locale) {
            $name = class_exists(Locale::class) ? Locale::getDisplayName($locale, $locale) : false;
            $name = is_string($name) && $name !== '' ? $name : $locale;
            $options[$locale] = mb_convert_case(mb_substr($name, 0, 1), MB_CASE_TITLE).mb_substr($name, 1);
        }

        return $options;
    }

    /**
     * The record's own language when the model provides one, else the app's, if supported.
     */
    private static function defaultLocale(Model $owner): ?string
    {
        $locale = method_exists($owner, 'publicLinkLocale') ? $owner->publicLinkLocale() : null;
        $locale = is_string($locale) && $locale !== '' ? $locale : app()->getLocale();

        return in_array($locale, PublicLinksConfig::supportedLocales(), true) ? $locale : null;
    }

    private static function appTimezone(): string
    {
        $timezone = config('app.timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : 'UTC';
    }

    private static function user(): ?Model
    {
        $user = auth()->user();

        return $user instanceof Model ? $user : null;
    }

    public static function label(string $key): string
    {
        $label = __('filament-public-links::filament-public-links.filament.'.$key);

        return is_string($label) ? $label : $key;
    }
}
