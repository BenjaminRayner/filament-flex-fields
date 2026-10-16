{{-- Initial document only: Livewire morphs acquire the same URLs from DOM consumers.
     These links are removable and become owned by actual cells, never by the head. --}}
@unless (request()->hasHeader('X-Livewire'))
    @foreach (\Bjanczak\FilamentFlexFields\Support\FlexFieldStylesheetQueue::criticalStylesheets() as $stylesheet)
        <link @include('filament-flex-fields::partials.asset-delivery-attributes', ['deliveryUrl' => \Bjanczak\FilamentFlexFields\Support\FlexFieldAssets::stylesheetHref($stylesheet)]) rel="stylesheet" blocking="render" data-fff-critical-asset data-fff-managed-asset="true" href="{{ \Bjanczak\FilamentFlexFields\Support\FlexFieldAssets::stylesheetHref($stylesheet) }}">
    @endforeach
@endunless
