@php
    $presentation = $field->getRatingPresentation();
    $interactive = ! $presentation['disabled'] && ! $presentation['readOnly'];
    $stateBinding = '$entangle('.\Illuminate\Support\Js::from($getStatePath()).')';
    $configuration = \Illuminate\Support\Arr::only($presentation, ['max', 'disabled', 'readOnly']);
    $identity = \Illuminate\Support\Arr::except($presentation, ['value', 'fills']);
    $selected = $presentation['value'];
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        @unless ($presentation['readOnly'])
            x-fff-load
            x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('rating-field', package: \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
            x-data="ratingFieldFormComponent({ ...@js($configuration), state: $wire.{{ $applyStateBindingModifiers($stateBinding) }} })"
        @endunless
        wire:key="{{ $getLivewireKey() }}.{{ hash('xxh128', serialize($identity)) }}"
        id="{{ $getId() }}"
        @class([
            'fff-rating', 'fff-rating--'.$presentation['size'],
            'fi-color-'.$presentation['color'], 'is-disabled' => $presentation['disabled'],
            'is-read-only' => $presentation['readOnly'],
            'fff-rating--with-value' => $presentation['readOnly'] && $presentation['showValue'],
            'is-interactive' => $interactive,
        ])
        role="{{ $presentation['readOnly'] ? 'img' : 'radiogroup' }}"
        aria-label="{{ $presentation['label'] }}{{ $presentation['readOnly'] ? ': '.number_format($selected ?? 0, 1).' / '.$presentation['max'] : '' }}"
        @unless ($presentation['readOnly'])
            aria-orientation="horizontal"
            aria-disabled="{{ $presentation['disabled'] ? 'true' : 'false' }}"
        @endunless
        {{ $getExtraAlpineAttributeBag() }}
    >
        @include('filament-flex-fields::partials.load-stylesheet', ['component' => $presentation['readOnly'] ? 'rating-visuals' : 'rating-field', 'livewireKey' => $getLivewireKey()])
        <div class="fff-rating__items">
            @foreach ($presentation['fills'] as $offset => $fillPercent)
                @php $index = $offset + 1; @endphp
                @if ($presentation['readOnly'])
                    <span class="fff-rating__item fff-rating__item--{{ $presentation['size'] }}" data-active="{{ $fillPercent > 0 ? 'true' : 'false' }}">
                        @include('filament-flex-fields::partials.rating.icon')
                    </span>
                @else
                    <button
                        type="button"
                        class="fff-rating__item fff-rating__item--{{ $presentation['size'] }}"
                        data-rating-index="{{ $index }}"
                        data-active="{{ $fillPercent > 0 ? 'true' : 'false' }}"
                        data-selected="{{ $selected === (float) $index ? 'true' : 'false' }}"
                        role="radio"
                        aria-checked="{{ $selected === (float) $index ? 'true' : 'false' }}"
                        aria-label="{{ trans_choice('filament-flex-fields::default.rating.stars', $index, ['count' => $index]) }}"
                        tabindex="{{ $interactive && ($selected === (float) $index || ($index === 1 && ($selected === null || $selected < 1))) ? '0' : '-1' }}"
                        @if ($presentation['disabled']) disabled @endif
                    >
                        @include('filament-flex-fields::partials.rating.icon')
                    </button>
                @endif
            @endforeach
        </div>
        @if ($presentation['readOnly'] && $presentation['showValue'])
            <span class="fff-rating__value">{{ number_format($selected ?? 0, 1) }}</span>
        @endif
    </div>
</x-dynamic-component>
