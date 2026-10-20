<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Support;

use Illuminate\Contracts\View\View;

/**
 * Renders a view of this package. Larastan cannot see package view namespaces registered by
 * hasViews(), so the view-string assertion lives here once.
 *
 * @internal
 */
final class PackageView
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function make(string $name, array $data = []): View
    {
        // The namespace is registered by hasViews(); Larastan only knows the app's view paths.
        return view('filament-public-links::'.$name, $data); // @phpstan-ignore argument.type
    }
}
