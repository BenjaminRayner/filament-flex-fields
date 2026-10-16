    <div wire:ignore class="fff-country-map__surface" role="group" aria-label="{{ __('filament-flex-fields::location.country_map') }}">
        <svg x-ref="world" x-bind:class="{ 'is-panning': panning }" x-bind:style="{ touchAction: zoom > 1 ? 'none' : 'pan-y' }" viewBox="0 0 900 440.70631074413296" x-bind:view-box.camel="viewBox" class="fff-country-map__world" xmlns="http://www.w3.org/2000/svg"
            x-on:pointerdown="beginPan($event)" x-on:pointermove="movePan($event)" x-on:pointerup="endPan($event)" x-on:pointercancel="endPan($event)" x-on:lostpointercapture="endPan($event)" x-on:pointerleave="tooltip = null" x-on:wheel="onWorldWheel($event)" x-on:keydown="onWorldKey($event)">
            @foreach ($field->getCountryMapPaths() as $countryCode => $countryPath)
                @php $countryAllowed = isset($countryMapAllowedSet[$countryCode]); @endphp
                <path d="{{ $countryPath }}" data-country="{{ $countryCode }}" role="checkbox" aria-label="{{ $countryMapNames[$countryCode]['label'] ?? $countryCode }}"
                    tabindex="{{ $countryAllowed && !$countryMapDisabled ? 0 : -1 }}" aria-disabled="{{ !$countryAllowed || $countryMapDisabled ? 'true' : 'false' }}"
                    aria-checked="{{ isset($countryMapSelectedSet[$countryCode]) ? 'true' : 'false' }}" x-bind:aria-checked="selected(@js($countryCode))"
                    @class(['fff-country-map__country', 'is-selected' => isset($countryMapSelectedSet[$countryCode]), 'is-unavailable' => !$countryAllowed])
                    x-bind:class="{ 'is-selected': selected(@js($countryCode)) }" x-on:pointerenter="showCountryTooltip($event)" x-on:focus="showCountryTooltip($event)" x-on:blur="tooltip = null" x-on:click.stop="toggleCountry(@js($countryCode))" x-on:keydown.enter.prevent="toggleCountry(@js($countryCode), true)" x-on:keydown.space.prevent="toggleCountry(@js($countryCode), true)">
                    <title>{{ $countryMapNames[$countryCode]['label'] ?? $countryCode }}</title>
                </path>
            @endforeach
        </svg>
        <div class="fff-country-map__tooltip" role="tooltip" x-show="tooltip" x-cloak x-bind:style="tooltipStyle"><span x-text="tooltip?.name"></span><span class="fff-country-map__tooltip-code" x-text="tooltip?.code"></span></div>
        <div class="fff-country-map__controls fff-map-controls"><div class="fff-map-controls__capsule fff-map-controls__capsule--vertical" role="group">
            <button type="button" class="fff-map-controls__button" x-on:click="zoomBy(0.5)" x-bind:disabled="zoom >= @js($countryMapCamera['maxZoom'])" aria-label="{{ __('filament-flex-fields::location.zoom_in') }}"><x-filament-flex-fields::map-icon name="zoom-in" /></button>
            <button type="button" class="fff-map-controls__button" x-on:click="zoomBy(-0.5)" x-bind:disabled="zoom <= @js($countryMapCamera['minZoom'])" aria-label="{{ __('filament-flex-fields::location.zoom_out') }}"><x-filament-flex-fields::map-icon name="zoom-out" /></button>
            <button type="button" class="fff-map-controls__button" x-on:click="resetView()" aria-label="{{ __('filament-flex-fields::location.reset') }}"><x-filament-flex-fields::map-icon name="reset" /></button>
        </div></div>
    </div>
