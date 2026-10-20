{{--
    The plain page for 404 and 410 answers. Step 3 of the design replaces it with the branded
    layout; the data it receives ($messageKey) stays the same. Never render the token here.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('filament-public-links::filament-public-links.errors.'.$messageKey) }}</title>
</head>
<body>
    <main>
        <p>{{ __('filament-public-links::filament-public-links.errors.'.$messageKey) }}</p>
    </main>
</body>
</html>
