@if ($card->image !== null)
    <div @class(['item-card__image', 'item-card__image--'.$card->imageShape]) data-slot="item-card-image">
        <img src="{{ $card->image }}" alt="{{ $card->imageAlt }}" loading="lazy" decoding="async" />
    </div>
@elseif ($card->icon !== null)
    <div class="item-card__icon" data-slot="item-card-icon" aria-hidden="true">
        <x-filament::icon :icon="$card->icon" />
    </div>
@endif
