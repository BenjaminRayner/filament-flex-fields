@php
    $colorHasEyedropper = $configuration['eyedropperEnabled'] && in_array($configuration['layout'], ['advanced', 'sliders'], true);
    $colorEyedropperInFormat = $colorHasEyedropper && $configuration['showFormatSelector'] && ($configuration['layout'] === 'sliders' || $configuration['showFields']);
@endphp
<div x-ref="panel" class="fff-color-panel" tabindex="-1" role="dialog" aria-label="{{ $label ?? __('filament-flex-fields::color-picker.value') }}" x-on:keydown="onPanelKey($event)" x-on:fff-color-format.stop="selectInputFormat($event.detail.format)"
    @if (!$colorInline)
        x-cloak x-show="panelOpen" x-bind:style="panelStyle"
        x-transition:enter="fff-color-enter" x-transition:enter-start="fff-color-enter-start" x-transition:enter-end="fff-color-enter-end"
        x-transition:leave="fff-color-leave" x-transition:leave-start="fff-color-leave-start" x-transition:leave-end="fff-color-leave-end"
    @else style="--fff-color-width: {{ $configuration['panelWidth'] }}px; --fff-color-panel-radius: {{ $configuration['panelRadius'] }}px" @endif>
    @if ($configuration['layout'] === 'advanced')
        <div class="fff-color-area" x-ref="saturationArea" tabindex="0" role="slider" aria-label="{{ __('filament-flex-fields::color-picker.saturation') }} / {{ __('filament-flex-fields::color-picker.brightness') }}" aria-valuemin="0" aria-valuemax="100" x-bind:aria-valuenow="Math.round(hsva.s)" x-bind:aria-valuetext="`${labels.saturation}: ${Math.round(hsva.s)}%, ${labels.brightness}: ${Math.round(hsva.v)}%`"
            x-bind:aria-disabled="readOnly" x-bind:style="{ background: saturationBackground }" x-on:pointerdown.prevent="startSaturationDrag($event)" x-on:pointermove="moveSaturation($event)" x-on:pointerup="endSaturation($event)" x-on:pointercancel="endSaturation($event, true)" x-on:lostpointercapture="endSaturation($event, true)" x-on:keydown="onSaturationKey($event)">
            <span class="fff-color-area-thumb" x-bind:style="saturationHandleStyle"></span>
        </div>
        <div class="fff-color-controls">
            @if ($colorHasEyedropper && !$colorEyedropperInFormat)
                @include('filament-flex-fields::forms.components.partials.color-picker.eyedropper')
            @endif
            <div class="fff-color-sliders">
                <div class="fff-color-channel">
                @if ($configuration['showChannelLabels'])
                    <div class="fff-color-channel-heading"><span>{{ __('filament-flex-fields::color-picker.hue') }}</span><output x-text="`${Math.round(hsva.h * 100) / 100}°`"></output></div>
                @endif
                <input class="fff-color-slider" type="range" min="0" max="360" step="0.1" aria-label="{{ __('filament-flex-fields::color-picker.hue') }}" x-bind:disabled="readOnly" x-bind:value="hsva.h" x-bind:style="{ background: hueGradient, '--fff-color-thumb': solidColor }" x-on:input="setChannel('h', $event.target.value)" x-on:change="commitState()" />
                </div>
                @if ($configuration['alphaEnabled'])
                    @include('filament-flex-fields::forms.components.partials.color-picker.opacity')
                @endif
            </div>
        </div>
    @endif
    @if ($configuration['layout'] === 'sliders')
        @if ($configuration['showFormatSelector'])
            @include('filament-flex-fields::forms.components.partials.color-picker.format')
        @endif
        <div class="fff-color-controls">
        @if ($colorHasEyedropper && !$colorEyedropperInFormat)
            @include('filament-flex-fields::forms.components.partials.color-picker.eyedropper')
        @endif
        <div class="fff-color-handoff" x-bind:class="{ 'is-ready': colorReady }">
            <div class="fff-color-sliders" data-fff-color-ssr aria-hidden="true">
                @for ($channelIndex = 0; $channelIndex < 3; $channelIndex++)
                    <div class="fff-color-channel">
                        @if ($configuration['showChannelLabels'])
                            <div class="fff-color-channel-heading"><span>&nbsp;</span><output>&nbsp;</output></div>
                        @endif
                        <div class="fff-color-slider"></div>
                    </div>
                @endfor
            </div>
            <div class="fff-color-sliders" data-fff-color-live>
            <template x-for="channel in sliderColorChannels()" x-bind:key="channel.key">
                <div class="fff-color-channel">
                    @if ($configuration['showChannelLabels'])
                        <div class="fff-color-channel-heading"><span x-text="channel.label"></span><output x-text="Math.round(channel.value) + channel.suffix"></output></div>
                    @endif
                    <input class="fff-color-slider" type="range" min="0" step="0.1" x-bind:max="channel.max" x-bind:aria-label="channel.label" x-bind:disabled="readOnly" x-bind:value="channel.value"
                        x-bind:style="{ background: channel.background, '--fff-color-thumb': solidColor }"
                        x-on:input="updateSliderChannel(channel.key, $event.target.value)" x-on:change="commitState()" />
                </div>
            </template>
            </div>
        </div>
        </div>
        @if ($configuration['alphaEnabled'])
            @include('filament-flex-fields::forms.components.partials.color-picker.opacity')
        @endif
    @endif
    @if (in_array($configuration['layout'], ['grid', 'swatches'], true) || $configuration['showSwatches'])
        <div class="fff-color-handoff" x-bind:class="{ 'is-ready': colorReady }" style="--fff-color-columns: {{ $configuration['layout'] === 'swatches' || $configuration['showSwatches'] ? 9 : $configuration['gridColumns'] }}">
            <div class="fff-color-grid {{ $configuration['layout'] === 'swatches' || $configuration['showSwatches'] ? 'is-swatches' : '' }}" data-fff-color-ssr aria-hidden="true">
                @foreach (\Bjanczak\FilamentFlexFields\Support\ColorPicker\ColorPickerPresentation::palette($configuration) as $paletteColor)
                    <span class="fff-color-grid-swatch" style="background-color: {{ $paletteColor }}"></span>
                @endforeach
            </div>
            <div class="fff-color-grid {{ $configuration['layout'] === 'swatches' || $configuration['showSwatches'] ? 'is-swatches' : '' }}" data-fff-color-live role="group" aria-label="{{ __('filament-flex-fields::color-picker.value') }}">
                <template x-for="(color, index) in gridPalette" x-bind:key="index"><button type="button" class="fff-color-grid-swatch" x-bind:style="{ backgroundColor: color }" x-bind:aria-label="color" x-bind:aria-pressed="isGridColorSelected(color)" x-bind:class="{ 'is-selected': isGridColorSelected(color) }" x-bind:disabled="readOnly" x-on:click="selectGridColor(color)"></button></template>
            </div>
        </div>
        @if (in_array($configuration['layout'], ['grid', 'swatches'], true) && $configuration['alphaEnabled'])
            @include('filament-flex-fields::forms.components.partials.color-picker.opacity')
        @endif
    @endif
    @if ($configuration['showFields'] && $configuration['layout'] !== 'sliders')
    @if ($configuration['channelFields'])
        @include('filament-flex-fields::forms.components.partials.color-picker.channels')
    @else
    @if ($configuration['showFormatSelector'])
        @include('filament-flex-fields::forms.components.partials.color-picker.format')
    @endif
    <div class="fff-color-fields without-format">
        <x-filament-flex-fields::location-input size="sm" type="text" maxlength="128" aria-label="{{ __('filament-flex-fields::color-picker.value') }}" x-model="valueInput" x-on:change="onValueInput()" x-on:blur="onValueInput()" x-on:keydown.enter.prevent="onValueInput()" x-bind:disabled="readOnly" x-bind:aria-invalid="Boolean(colorError)" />
        @if ($configuration['alphaEnabled'])
            <div class="fff-color-opacity-field"><x-filament-flex-fields::location-input size="sm" type="number" min="0" max="100" step="1" aria-label="{{ __('filament-flex-fields::color-picker.opacity') }}" x-model="opacityInput" x-on:change="onOpacityInput()" x-on:blur="onOpacityInput()" x-on:keydown.enter.prevent="onOpacityInput()" x-bind:disabled="readOnly" /></div>
        @endif
    </div>
    @endif
    @endif
    <p class="fff-color-error" role="alert" x-show="colorError" x-text="colorError" x-cloak></p>
</div>
