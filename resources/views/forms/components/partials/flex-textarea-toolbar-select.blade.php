@php
    $controlId = $getId().'-toolbar-'.hash('xxh128', $select['statePath']);
    $stateBinding = '$entangle('.\Illuminate\Support\Js::from($select['statePath']).')';
    $selectOptions = [];
    foreach ($select['options'] as $value => $label) {
        $selectOptions[] = ['value' => (string) $value, 'label' => $label];
    }
@endphp
<div
    class="fff-flex-textarea__select"
    wire:ignore
    wire:key="{{ $controlId }}.{{ hash('xxh128', serialize([$selectOptions, $isDisabled, $isReadOnly])) }}"
    x-data="{ value: $wire.{{ $applyStateBindingModifiers($stateBinding) }} }"
>
    <x-filament-flex-fields::select-control
        :control-id="$controlId" :options="$selectOptions"
        state-expression="value" :label="$select['placeholder']"
        :placeholder="$select['placeholder']" :disabled-initially="$isDisabled || $isReadOnly"
        :disabled-expression="$isDisabled || $isReadOnly ? 'true' : 'false'"
        :dropdown-min-width="220" :match-trigger-width="false"
        on-change="values => { value = values[0] ?? null }"
    >
        <x-slot:trigger>
            <button type="button" class="fff-flex-textarea__select-trigger" role="combobox"
                x-ref="headlessTrigger" data-fff-open-trigger aria-haspopup="listbox"
                aria-label="{{ $select['placeholder'] }}" @disabled($isDisabled || $isReadOnly)
                x-bind:aria-controls="menuDomId + '-listbox'" x-bind:aria-expanded="comboboxOpen" x-bind:disabled="disabled"
                x-bind:aria-activedescendant="headlessActiveDescendant()"
                x-on:click="onHeadlessTriggerClick($event)" x-on:keydown="onHeadlessControlKeydown($event)"
            >
                @if (filled($select['icon']))
                    <span class="fff-flex-textarea__select-icon" aria-hidden="true">{{ \Filament\Support\generate_icon_html($select['icon'], size: \Filament\Support\Enums\IconSize::Small) }}</span>
                @endif
                <span class="fff-flex-textarea__select-value" x-text="triggerLabelHtml()">{{ $select['initialLabel'] }}</span>
                <x-filament::icon icon="hugeicons-arrow-down-01" class="fff-flex-textarea__select-chevron" x-bind:class="{ 'is-open': comboboxOpen }" />
            </button>
        </x-slot:trigger>
    </x-filament-flex-fields::select-control>
</div>
