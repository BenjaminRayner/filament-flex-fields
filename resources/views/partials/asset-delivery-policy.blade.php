@if (app(\Bjanczak\FilamentFlexFields\Support\Assets\Rendering\RuntimeRenderContext::class)->claim(\Bjanczak\FilamentFlexFields\Support\Assets\Rendering\RuntimeEmission::DeliveryPolicy))
@php
    $documentDelivery = app(\Bjanczak\FilamentFlexFields\Support\Assets\Delivery\AssetDeliveryContext::class);
    $deliveryImportMap = $documentDelivery->importMap();
    $documentDeliveryNonce = $documentDelivery->nonce();
@endphp
<template data-fff-delivery-policy="{{ $documentDelivery->policy()->json() }}" @if ($documentDeliveryNonce !== null) data-fff-csp-nonce="{{ $documentDeliveryNonce }}" @endif></template>
<template data-fff-style-order="{{ app(\Bjanczak\FilamentFlexFields\Support\Assets\Rendering\StylesheetOrderCatalog::class)->json() }}"></template>
@if ($deliveryImportMap !== null)
<script type="importmap" @if ($documentDeliveryNonce !== null) nonce="{{ $documentDeliveryNonce }}" @endif>{!! $deliveryImportMap !!}</script>
@endif
@endif
