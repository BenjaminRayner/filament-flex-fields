@php
    use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
    use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
    use Filament\Support\Facades\FilamentAsset;
@endphp

@if (app(\Bjanczak\FilamentFlexFields\Support\Assets\Rendering\RuntimeRenderContext::class)->claim(\Bjanczak\FilamentFlexFields\Support\Assets\Rendering\RuntimeEmission::Injector))
@php
    $src = FilamentAsset::getScriptSrc(
        FlexFieldAssets::ASSET_INJECTOR_SCRIPT_ID,
        FilamentFlexFieldsPlugin::PACKAGE_NAME,
    );

    $delivery = app(\Bjanczak\FilamentFlexFields\Support\Assets\Delivery\AssetDeliveryContext::class);
    $runtimeSrc = FlexFieldAssets::alpineEntrySrc('flex-core-runtime');
    $runtimeAttributes = $delivery->attributes($runtimeSrc);
@endphp

<span hidden data-fff-asset-messages data-fff-asset-error="{{ __('filament-flex-fields::diagnostics.asset_error') }}" data-fff-asset-retry="{{ __('filament-flex-fields::diagnostics.asset_retry') }}"></span>
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
