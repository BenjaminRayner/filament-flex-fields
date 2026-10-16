@php
    $cover = $getCoverCardRenderState();
    $cardAttributes = $attributes
        ->merge(['id' => $getId()], escape: false)
        ->merge($getExtraAttributes(), escape: false)
        ->style($cover->styles)
        ->class([
            'fff-cover-card',
            'fff-cover-card--tone-'.$cover->tone,
            'fff-cover-card--radius-'.$cover->radius,
            'is-full-width' => $cover->fullWidth,
            'is-mobile-full-width' => $cover->fullWidthOnMobile,
        ]);
@endphp

@include('filament-flex-fields::partials.load-stylesheet', [
    'component' => 'cover-card',
    'assetSchemaComponent' => $schemaComponent,
    'livewireKey' => $getLivewireKey(),
])

<div {{ $cardAttributes }} data-slot="cover-card">
    @include('filament-flex-fields::schemas.components.cover-card.background')
    <div class="fff-cover-card__content">
        @include('filament-flex-fields::schemas.components.cover-card.top')
        @include('filament-flex-fields::schemas.components.cover-card.footer')
    </div>
</div>
