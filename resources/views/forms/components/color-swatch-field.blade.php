@php
    $presentation = $field->getSwatchPresentation();
    $binding = '$entangle('.\Illuminate\Support\Js::from($getStatePath()).')';
    $configuration = ['keys' => $presentation['keys'], 'disabled' => $presentation['disabled']];
    $identity = array_map(static fn (array $item): array => \Illuminate\Support\Arr::except($item, ['selected']), $presentation['items']);
    $selectedKey = collect($presentation['items'])->firstWhere('selected', true)['key'] ?? ($presentation['keys'][0] ?? null);
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        id="{{ $getId() }}"
        wire:key="{{ $getLivewireKey() }}.{{ hash('xxh128', serialize([$identity, $presentation['disabled'], $presentation['tooltips']])) }}"
        x-fff-load
        x-fff-load-src="{{ \Bjanczak\FilamentFlexFields\Support\FlexFieldAssets::alpineEntrySrc('color-swatch') }}"
        x-data="colorSwatchFormComponent({ ...@js($configuration), state: $wire.{{ $applyStateBindingModifiers($binding) }} })"
        x-on:keydown="keydown($event)"
        @class(['fff-color-swatch', 'fff-color-swatch--'.$presentation['size'], 'is-disabled' => $presentation['disabled']])
        role="radiogroup"
        aria-label="{{ $getLabel() }}"
        aria-orientation="horizontal"
        aria-disabled="{{ $presentation['disabled'] ? 'true' : 'false' }}"
        {{ $getExtraAlpineAttributeBag() }}
    >
        @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'color-swatch', 'livewireKey' => $getLivewireKey()])
        @if (filled($presentation['label']) || filled($presentation['icon']))
            <div class="fff-color-swatch__header">
                @if (filled($presentation['icon']))
                    <span class="fff-color-swatch__header-icon" aria-hidden="true">{{ \Filament\Support\generate_icon_html($presentation['icon']) }}</span>
                @endif
                @if (filled($presentation['label']))
                    <span class="fff-color-swatch__header-label">{{ $presentation['label'] }}</span>
                @endif
            </div>
        @endif
        <div class="fff-color-swatch__pills">
            @foreach ($presentation['items'] as $item)
                <button
                    type="button"
                    @class(['fff-color-swatch__pill', 'is-selected' => $item['selected']])
                    style="--fff-color-swatch-pill: {{ $item['color'] }}"
                    data-swatch-value="{{ $item['key'] }}"
                    role="radio"
                    aria-label="{{ __('filament-flex-fields::default.color_swatch.select', ['color' => $item['label']]) }}"
                    aria-checked="{{ $item['selected'] ? 'true' : 'false' }}"
                    tabindex="{{ ! $presentation['disabled'] && $selectedKey === $item['key'] ? '0' : '-1' }}"
                    x-bind:class="{ 'is-selected': isSelected(@js($item['key'])) }"
                    x-bind:aria-checked="isSelected(@js($item['key']))"
                    x-bind:tabindex="tabIndex(@js($item['key']))"
                    x-on:click="select(@js($item['key']))"
                    @if ($presentation['disabled']) disabled @endif
                    @if ($presentation['tooltips'])
                        x-tooltip="{ content: @js($item['label']), theme: 'fff fff-color-swatch', arrow: false, allowHTML: false }"
                    @endif
                ></button>
            @endforeach
        </div>
    </div>
</x-dynamic-component>
