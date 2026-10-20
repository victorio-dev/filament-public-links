{{--
    The 404 and 410 pages, in the branded layout. $link is null on 404. The contact sentence only
    appears on 410 (an existing link that ended), never on 404, and only when an address is set.
--}}
@php
    $message = __('filament-public-links::filament-public-links.errors.'.$messageKey);
@endphp
<x-filament-public-links::layout :link="$link" :page-title="$message" :branding="$branding">
    <x-slot:title>{{ $message }}</x-slot:title>

    @if ($status === 410)
        @if ($branding->contactEmail !== null)
            {{-- The sentence is escaped first; only the address is turned into a link, itself escaped. --}}
            <p>{!! str_replace(
                '%email%',
                '<a href="mailto:'.e($branding->contactEmail).'">'.e($branding->contactEmail).'</a>',
                e(__('filament-public-links::filament-public-links.errors.contact', ['email' => '%email%'])),
            ) !!}</p>
        @endif
    @endif
</x-filament-public-links::layout>
