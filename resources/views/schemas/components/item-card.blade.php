@php
    $card = $getItemCardRenderState();
    $tag = $card->tag();
    $rowAttributes = $attributes->merge(['id' => $getId()], escape: false)
        ->merge($getExtraAttributes(), escape: false)
        ->class([
            'item-card',
            'item-card--'.$card->variant,
            'item-card--context-'.$card->context,
            'item-card--pressable' => $card->pressable,
        ]);
    if ($tag === 'a') {
        $rowAttributes = $rowAttributes->merge([
            'href' => $card->url,
            'target' => $card->newTab ? '_blank' : null,
            'rel' => $card->newTab ? 'noopener noreferrer' : null,
        ]);
    } elseif ($tag === 'button') {
        $rowAttributes = $rowAttributes->merge(['type' => 'button']);
        $handler = $card->action?->getLivewireClickHandler();
        if (filled($handler)) {
            $rowAttributes = $rowAttributes->merge([
                'wire:click' => $handler,
                'wire:target' => $handler,
                'wire:loading.attr' => 'disabled',
                'disabled' => $card->action->isDisabled(),
            ]);
        }
    }
    if ($card->pressable) {
        $rowAttributes = $rowAttributes->merge([
            'x-fff-load' => true,
            'x-fff-load-src' => \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('item-card-interaction', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME),
            'x-data' => 'itemCardInteraction()',
        ]);
    }
@endphp
@include('filament-flex-fields::partials.load-stylesheet', [
    'component' => 'item-card',
    'capabilities' => $card->pressable ? ['pressable'] : [],
    'assetSchemaComponent' => $schemaComponent,
    'livewireKey' => $getLivewireKey(),
])
<{{ $tag }} {{ $rowAttributes }} data-slot="item-card">
    @include('filament-flex-fields::schemas.components.item-card.leading')
    <div class="item-card__content" data-slot="item-card-content">
        @if (filled($card->heading))
            <span class="item-card__title" data-slot="item-card-title">{{ $card->heading }}</span>
        @endif
        @if (filled($card->description))
            <span class="item-card__description" data-slot="item-card-description">{{ $card->description }}</span>
        @endif
    </div>
    <div class="item-card__action fi-fixed-positioning-context" data-slot="item-card-action">
        {{ $card->childSchema }}
        @if ($card->chevron !== null)
            <span class="item-card__chevron" data-slot="item-card-chevron" aria-hidden="true">
                <x-filament::icon :icon="$card->chevron" />
            </span>
        @endif
    </div>
</{{ $tag }}>
