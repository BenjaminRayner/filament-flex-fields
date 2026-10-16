<div class="fff-color-channel-fields">
    @if ($configuration['showFormatSelector'])
        @include('filament-flex-fields::forms.components.partials.color-picker.format')
    @endif
    <div class="fff-color-channel-inputs" @if ($configuration['format'] === 'hex') style="display: none" @endif x-show="displayColorChannels().length">
        <template x-for="channel in displayColorChannels()" x-bind:key="channel.key">
            <label class="fff-color-channel-field"><x-filament-flex-fields::location-input size="sm" variant="soft" type="number" min="0" step="0.01" x-bind:max="channel.max" x-bind:aria-label="channel.label" x-bind:value="channel.value" x-bind:disabled="readOnly" x-on:keydown.enter.prevent="updateColorChannel(channel.key, $event.target.value)" x-on:blur="updateColorChannel(channel.key, $event.target.value)" x-on:change="updateColorChannel(channel.key, $event.target.value); $event.target.value = displayColorChannels().find(item => item.key === channel.key)?.value ?? channel.value" /><span class="fff-color-channel-suffix" aria-hidden="true" x-text="channel.suffix"></span></label>
        </template>
    </div>
    <div @if ($configuration['format'] !== 'hex') style="display: none" @endif x-show="!displayColorChannels().length"><x-filament-flex-fields::location-input size="sm" type="text" maxlength="128" aria-label="{{ __('filament-flex-fields::color-picker.value') }}" x-model="valueInput" x-on:change="onValueInput()" x-on:blur="onValueInput()" x-on:keydown.enter.prevent="onValueInput()" x-bind:disabled="readOnly" x-bind:aria-invalid="Boolean(colorError)" /></div>
</div>
