@php
    use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
    use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
    use Filament\Support\Facades\FilamentAsset;
@endphp

@if (app(\Bjanczak\FilamentFlexFields\Support\Assets\Rendering\RuntimeRenderContext::class)->claim(\Bjanczak\FilamentFlexFields\Support\Assets\Rendering\RuntimeEmission::Bootstrap))
@php
    $src = FilamentAsset::getScriptSrc(
        FlexFieldAssets::FFF_LOAD_BOOTSTRAP_SCRIPT_ID,
        FilamentFlexFieldsPlugin::PACKAGE_NAME,
    );

    $delivery = app(\Bjanczak\FilamentFlexFields\Support\Assets\Delivery\AssetDeliveryContext::class);
    $runtimeSrc = FlexFieldAssets::alpineEntrySrc('flex-core-runtime');
    $runtimeAttributes = $delivery->attributes($runtimeSrc);
@endphp

@include('filament-flex-fields::partials.asset-delivery-policy')
@if (isset($runtimeAttributes['integrity']))
<link rel="modulepreload" href="{{ $runtimeSrc }}" @include('filament-flex-fields::partials.asset-delivery-attributes', ['deliveryUrl' => $runtimeSrc])>
@endif
<script
    @include('filament-flex-fields::partials.asset-delivery-attributes', ['deliveryUrl' => $src])
    src="{{ $src }}"
    data-fff-runtime-src="{{ $runtimeSrc }}"
    data-navigate-track
></script>
@endif
