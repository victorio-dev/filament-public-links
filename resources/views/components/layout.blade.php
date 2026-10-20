{{--
    The branded public layout. Everything comes from $branding (a cleaned Data\Branding), so the
    colours inside <style> are always #rrggbb and every text is escaped. No external assets, no
    JavaScript. Hosts restyle through config or a BrandingResolver; publishing this view is the
    last resort and stops it from receiving updates.

    Slots: $title (the page heading), the default slot (content), $footer (extra footer line).
--}}
@php
    $title = isset($title) && trim((string) $title) !== '' ? $title : null;
    $documentTitle = trim(($pageTitle !== null && $pageTitle !== '' ? $pageTitle.' · ' : '').$branding->name, ' ·');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>{{ $documentTitle }}</title>
    <style>
        :root {
            color-scheme: light dark;
            --fpl-primary: {{ $branding->primaryColor }};
            --fpl-on-primary: {{ $branding->onPrimaryColor }};
            --fpl-bg: #ffffff;
            --fpl-surface: #f9fafb;
            --fpl-border: #e5e7eb;
            --fpl-text: #111827;
            --fpl-muted: #4b5563;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --fpl-bg: #111827;
                --fpl-surface: #1f2937;
                --fpl-border: #374151;
                --fpl-text: #f3f4f6;
                --fpl-muted: #9ca3af;
            }
        }
        *, *::before, *::after { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--fpl-bg);
            color: var(--fpl-text);
            font: 1rem/1.6 system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .fpl-header { background: var(--fpl-primary); color: var(--fpl-on-primary); }
        .fpl-header-inner, .fpl-main, .fpl-footer-inner {
            width: 100%;
            max-width: 42rem;
            margin: 0 auto;
            padding: 1rem 1.25rem;
        }
        .fpl-brand { display: flex; align-items: center; gap: .75rem; font-weight: 600; font-size: 1.05rem; }
        .fpl-logo { display: block; max-height: 2.25rem; max-width: 10rem; width: auto; height: auto; }
        .fpl-main { flex: 1; padding-top: 2rem; padding-bottom: 2rem; }
        .fpl-main h1 { font-size: 1.6rem; line-height: 1.25; margin: 0 0 1rem; overflow-wrap: anywhere; }
        .fpl-main p { margin: 0 0 1rem; }
        .fpl-main a, .fpl-footer a { color: inherit; text-decoration: underline; text-underline-offset: .15em; }
        .fpl-main a:focus-visible, .fpl-footer a:focus-visible { outline: 2px solid currentColor; outline-offset: 2px; }
        .fpl-footer { border-top: 1px solid var(--fpl-border); background: var(--fpl-surface); color: var(--fpl-muted); font-size: .875rem; }
        .fpl-footer p { margin: 0 0 .25rem; }
        @media (max-width: 30rem) {
            .fpl-main h1 { font-size: 1.35rem; }
        }
    </style>
</head>
<body>
    <header class="fpl-header">
        <div class="fpl-header-inner">
            <div class="fpl-brand">
                @if ($branding->logoUrl !== null)
                    <img class="fpl-logo" src="{{ $branding->logoUrl }}" alt="{{ $branding->name }}">
                @else
                    <span>{{ $branding->name }}</span>
                @endif
            </div>
        </div>
    </header>

    <main class="fpl-main" id="content">
        @if ($title !== null)
            <h1>{{ $title }}</h1>
        @endif

        {{ $slot }}
    </main>

    <footer class="fpl-footer">
        <div class="fpl-footer-inner">
            @isset($footer)
                @if (trim((string) $footer) !== '')
                    <p>{{ $footer }}</p>
                @endif
            @endisset
            @if ($branding->footerText !== null)
                <p>{{ $branding->footerText }}</p>
            @endif
            @if ($branding->showPoweredBy)
                <p>{{ __('filament-public-links::filament-public-links.layout.powered_by') }}</p>
            @endif
        </div>
    </footer>
</body>
</html>
