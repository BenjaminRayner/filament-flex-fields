@php
    $dayState = $initialState['days'][$day];
    $enabled = $dayState['enabled'];
    $locked = in_array($day, $config['lockedDays'], true);
@endphp
<div @class(['fff-schedule-field__day', 'is-open' => $enabled]) x-bind:class="{ 'is-open': isDayEnabled(@js($day)), 'has-day-error': dayValidationError(@js($day)) }">
    <div class="fff-schedule-field__day-header">
        <span class="fff-schedule-field__day-label">{{ $config['dayLabels'][$day] }}</span>
        <span class="fff-schedule-field__day-status" x-text="isDayEnabled(@js($day)) ? labels.open : labels.closed">{{ $enabled ? $labels['open'] : $labels['closed'] }}</span>
        <div class="fff-schedule-field__day-header-spacer" aria-hidden="true"></div>
        @if ($hasCopyColumn)
            <div @class(['fff-schedule-field__copy-slot', 'fff-schedule-field__copy-slot--active' => $day === $config['copySourceDay']])>
                @if ($day === $config['copySourceDay'])
                    <button type="button" class="fff-schedule-field__copy-btn" x-bind:class="{ 'is-hidden': !shouldShowCopyButton(@js($day)) }" x-on:click="copySourceToWeekdays()" x-bind:disabled="!isInteractive" @disabled($disabled || $readOnly) aria-label="{{ $labels['copyToWeekdays'] }}" title="{{ $labels['copyToWeekdays'] }}">
                        <x-filament::icon :icon="\Bjanczak\FilamentFlexFields\Support\FlexIcon::Copy" class="h-3.5 w-3.5" /><span class="fff-schedule-field__copy-btn-label">{{ $labels['copyToWeekdays'] }}</span>
                    </button>
                @endif
            </div>
        @endif
        @unless ($readOnly)
            @if ($locked)
                <span class="fff-schedule-field__day-lock" title="{{ __('filament-flex-fields::default.schedule.day_locked') }}"><x-filament::icon :icon="\Bjanczak\FilamentFlexFields\Support\FlexIcon::Lock" class="fff-schedule-field__day-lock-icon" /></span>
            @else
                @php
                    $daySwitch = $field->makeDaySwitch($day);
                    $presentation = $daySwitch->getSwitchPresentation();
                    $presentation['checked'] = $enabled;
                    $switchConfiguration = \Illuminate\Support\Arr::only($presentation, ['disabled', 'ripple', 'onClasses', 'offClasses']);
                @endphp
                <div @class(['fff-switch fff-switch--sm fff-switch--inline fff-schedule-field__day-switch', 'is-disabled' => $presentation['disabled']])
                    x-fff-load x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('switch', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
                    x-data="switchFormComponent({ ...@js($switchConfiguration), state: @js($enabled),
                        getValue: () => isDayEnabled(@js($day)),
                        setValue: (value) => { if (value !== isDayEnabled(@js($day))) toggleDay(@js($day)) },
                        getDisabled: () => !isInteractive || isDayLocked(@js($day)) })"
                    x-bind:class="{ 'is-disabled': isDisabled }">
                    @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'switch', 'livewireKey' => $getLivewireKey().'.day-'.$day, 'assetSchemaComponent' => $daySwitch])
                    <x-filament-flex-fields::switch-control :presentation="$presentation" :id="$daySwitch->getId()"
                        wire:loading.attr="disabled" wire:target="{{ $getStatePath() }}" />
                </div>
            @endif
        @endunless
    </div>
    @if ($enabled)
        <div class="fff-schedule-field__day-body-ssr" x-bind:class="{ 'is-replaced': displayReady }" aria-hidden="true" inert>
            <div class="fff-schedule-field__slots">
                @php $slotNumber = 0; @endphp
                @foreach ($dayState['slots'] as $slot)
                    @php $isBreak = ($slot['type'] ?? 'slot') === 'break'; $slotCaption = $isBreak ? $labels['break'] : $labels['slot'].' '.(++$slotNumber); @endphp
                    @include('filament-flex-fields::forms.components.partials.schedule.slot', ['static' => true, 'slot' => $slot, 'slotCaption' => $slotCaption])
                @endforeach
            </div>
            @include('filament-flex-fields::forms.components.partials.schedule.toolbar', ['static' => true])
        </div>
    @endif
    <div class="fff-schedule-field__day-body-live" x-bind:class="{ 'is-ready': displayReady }">
        <div @class(['fff-schedule-field__day-collapse', 'is-expanded' => $enabled]) x-bind:class="{ 'is-expanded': isDayEnabled(@js($day)) }" x-bind:aria-hidden="!isDayEnabled(@js($day))" x-bind:inert="!isDayEnabled(@js($day))">
            <div class="fff-schedule-field__day-collapse-inner">
                <div class="fff-schedule-field__slots">
                    <template x-for="(slot, slotIndex) in daySlots(@js($day))" x-bind:key="`${@js($day)}-${slotIndex}`">
                        @include('filament-flex-fields::forms.components.partials.schedule.slot', ['static' => false, 'slot' => [], 'slotCaption' => ''])
                    </template>
                </div>
                @include('filament-flex-fields::forms.components.partials.schedule.toolbar', ['static' => false])
                <p class="fff-schedule-field__day-error" x-show="dayValidationErrorMessage(@js($day))" x-text="dayValidationErrorMessage(@js($day))" role="alert" x-cloak></p>
            </div>
        </div>
    </div>
</div>
