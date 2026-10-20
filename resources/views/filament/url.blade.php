{{--
    The public URL with a copy button, shown after a link is created and by the "Copy link" action.
    The URL is only rendered here, when an admin asked for it; never into the links table.
    Copying falls back to selecting the text when the Clipboard API is unavailable (plain HTTP)
    or refuses to write.
--}}
@php
    /** @var \Victorio\PublicLinks\Models\PublicLink|null $link */
    $t = fn (string $key, array $replace = []): string => __('filament-public-links::filament-public-links.filament.actions.url.'.$key, $replace);
    $fieldId = 'fpl-url-'.\Illuminate\Support\Str::random(8);
    $url = $link?->url();
@endphp
<div
    x-data="{
        copied: false,
        manual: false,
        async copy() {
            const field = this.$refs.url;
            try {
                if (! window.isSecureContext || ! navigator.clipboard) {
                    throw new Error('clipboard unavailable');
                }
                await navigator.clipboard.writeText(field.value);
                this.manual = false;
                this.copied = true;
                setTimeout(() => this.copied = false, 2000);
            } catch (error) {
                field.focus();
                field.select();
                this.manual = true;
            }
        },
    }"
    style="display: grid; gap: .75rem;"
>
    @if ($url === null)
        <p>{{ $t('unavailable') }}</p>
    @else
        <p>{{ $t('description') }}</p>

        <label for="{{ $fieldId }}" style="font-weight: 500;">{{ $t('field') }}</label>
        <div style="display: flex; gap: .5rem; align-items: center;">
            <x-filament::input.wrapper style="flex: 1; min-width: 0;">
                <x-filament::input
                    type="text"
                    :id="$fieldId"
                    :value="$url"
                    readonly
                    x-ref="url"
                    x-on:focus="$event.target.select()"
                    x-on:click="$event.target.select()"
                />
            </x-filament::input.wrapper>

            <x-filament::button type="button" color="gray" x-on:click="copy()">
                <span x-show="! copied">{{ $t('copy') }}</span>
                <span x-show="copied" x-cloak>{{ $t('copied') }}</span>
            </x-filament::button>
        </div>

        <p x-show="manual" x-cloak role="status" style="font-size: .875rem;">{{ $t('manual') }}</p>

        <div style="font-size: .875rem;">
            <p>
                {{ $link->expires_at === null
                    ? $t('never_expires')
                    : $t('expires', ['date' => \Victorio\PublicLinks\Filament\Support\DisplayDate::format($link->expires_at)]) }}
            </p>
            @if ($link->max_uses !== null)
                <p>{{ $t('usage', ['max' => $link->max_uses, 'used' => $link->use_count]) }}</p>
            @endif
        </div>
    @endif
</div>
