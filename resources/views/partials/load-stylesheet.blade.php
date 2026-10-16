@php
    use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetPlan;
    use Bjanczak\FilamentFlexFields\Support\FlexFieldAlpineQueue;
    use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
    use Bjanczak\FilamentFlexFields\Support\FlexFieldStylesheetQueue;

    $assetPlan = ComponentAssetPlan::for($component, $capabilities ?? []);

    // A rendered, server-visible schema instance reserves its final CSS geometry
    // before first paint. Hidden scopes and Livewire/modal updates remain lazy.
    if (\Bjanczak\FilamentFlexFields\Support\Assets\InitialSchemaVisibility::allows($assetSchemaComponent ?? $field ?? null)) {
        FlexFieldStylesheetQueue::requireCritical($component, $capabilities ?? []);
        // Head links block first paint; only declared external CSS still needs
        // browser readiness. Factory/modal readiness remains independent.
        $initialCssReady = count($externalStylesheets ?? []) === 0;
    }

    foreach ($assetPlan->components as $assetComponent) {
        FlexFieldStylesheetQueue::enqueueFor($assetComponent);
        FlexFieldAlpineQueue::enqueueChunksFor($assetComponent);
    }

    $stylesheets = $assetPlan->stylesheets;
    $chunks = $assetPlan->chunks;
    $resolvedLivewireKey = FlexFieldAssets::resolveAssetConsumerLivewireKey(
        $component,
        $livewireKey ?? null,
    );
@endphp


{{-- Emit the full root URL set (not only newly pending) so each field instance CRG-owns
     every dependency — shared deps stay retained if a sibling/modal consumer releases.
     resolveAssetConsumerLivewireKey() guarantees a non-blank CRG id even for keyless schema
     components (ItemCardStack::make()). --}}
@if (count($stylesheets) > 0 || count($chunks) > 0 || count($externalStylesheets ?? []) > 0)
    @include('filament-flex-fields::partials.emit-assets', [
        'stylesheets' => $stylesheets,
        'chunks' => $chunks,
        'externalStylesheets' => $externalStylesheets ?? [],
        'consumerComponent' => $component,
        'livewireKey' => $resolvedLivewireKey,
        'factoryOwners' => \Bjanczak\FilamentFlexFields\Support\Assets\Rendering\RenderedFactoryOwners::forComponents($assetPlan->components),
    ])

    @php
        FlexFieldStylesheetQueue::markStylesheetsEmitted($stylesheets);
        FlexFieldAlpineQueue::markChunksEmitted($chunks);
    @endphp
@endif
