@php
    use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
    // Component styles belong to the same demand-driven runtime used in production.
    // A concatenated demo bundle would download the same rules under a second URL.
    $playgroundStylesheetHrefs = [FlexFieldAssets::playgroundStylesheetHref()];
    $playgroundSlug = FlexFieldAssets::resolvePlaygroundSlugFromRequest();
@endphp

@foreach ($playgroundStylesheetHrefs as $playgroundStylesheetHref)
    @if (filled($playgroundStylesheetHref))
        @pushOnce('styles', 'fff-playground-base:'.$playgroundStylesheetHref)
            <link
                @include('filament-flex-fields::partials.asset-delivery-attributes', ['deliveryUrl' => $playgroundStylesheetHref])
                rel="stylesheet"
                href="{{ $playgroundStylesheetHref }}"
                data-navigate-track
                data-fff-playground-bundle
                @if (filled($playgroundSlug))
                    data-fff-playground-slug="{{ $playgroundSlug }}"
                @endif
            />
        @endPushOnce
    @endif
@endforeach
