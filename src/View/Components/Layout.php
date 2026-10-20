<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Victorio\PublicLinks\Data\Branding;
use Victorio\PublicLinks\Models\PublicLink;
use Victorio\PublicLinks\Support\PublicLinksConfig;

/**
 * The branded public layout, `<x-filament-public-links::layout>`:
 *
 *     <x-filament-public-links::layout :link="$visit->link" :page-title="$quote->number">
 *         <x-slot:title>{{ $quote->number }}</x-slot:title>
 *         …content…
 *         <x-slot:footer>Questions? Call us.</x-slot:footer>
 *     </x-filament-public-links::layout>
 *
 * The tag and its props are public API; the class is not meant to be extended.
 *
 * @internal
 */
final class Layout extends Component
{
    public Branding $branding;

    /**
     * Pass `branding` when it is already resolved (the 404/410 pages do), so the resolver runs
     * once per request.
     */
    public function __construct(
        public ?PublicLink $link = null,
        public ?string $pageTitle = null,
        ?Branding $branding = null,
    ) {
        $this->branding = $branding ?? app(PublicLinksConfig::brandingResolver())->resolve($link);
    }

    public function render(): View
    {
        // A package view namespace Larastan cannot see; it is registered by hasViews().
        /** @var view-string $view */
        $view = 'filament-public-links::components.layout';

        return view($view);
    }
}
