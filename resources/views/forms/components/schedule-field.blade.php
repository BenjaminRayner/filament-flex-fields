@php
    $statePath = $getStatePath();
    $disabled = $isDisabled();
    $readOnly = $isReadOnly();
    $size = $getSize();
    $variant = $getVariant();
    $config = $field->getAlpineConfiguration();
    $initialState = $field->normalizeState($getState());
    $labels = $config['labels'];
    $timeOptions = $field->getTimeOptions();
    $timezoneControl = $field->makeTimezoneControl();
    $hasCopyColumn = $config['allowCopyToWeekdays'] && in_array($config['copySourceDay'], $config['days'], true)
        && count(array_diff(array_intersect($config['weekdays'], $config['days']), [$config['copySourceDay']])) > 0;
    $statusWidth = max(mb_strlen($labels['open']), mb_strlen($labels['closed']));
    $slotWidth = max(mb_strlen($labels['slot'].' '.$config['maxSlots']), mb_strlen($labels['break'])) + 1;
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field" :attributes="\Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())->class($getWrapperClasses())">
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'schedule-field', 'livewireKey' => $getLivewireKey()])
    <div wire:ignore wire:key="{{ $getLivewireKey() }}.{{ hash('xxh128', serialize([$disabled, $readOnly, $size, $variant, $config])) }}"
        x-fff-load x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('schedule-field', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
        x-data="scheduleFieldFormComponent({ state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }}, statePath: @js($statePath), disabled: @js($disabled), readOnly: @js($readOnly), initialState: @js($initialState), ...@js($config) })"
        @class(['fff-schedule-field', 'fff-schedule-field--'.$size, 'fff-schedule-field--'.$variant, 'fff-schedule-field--has-copy-column' => $hasCopyColumn, 'is-disabled' => $disabled, 'is-read-only' => $readOnly, 'has-focus-outline' => $shouldShowFocusOutline(), 'has-error' => filled($statePath) && $errors->has($statePath)])
        x-bind:class="{ 'is-day-animated': dayAnimationsEnabled }"
        style="--fff-schedule-status-min-ch: {{ $statusWidth }}; --fff-schedule-copy-min-ch: {{ mb_strlen($labels['copyToWeekdays']) + 3 }}; --fff-schedule-slot-label-w: calc({{ $slotWidth }} * 1ch + 1.75rem);"
        @if ($getScheduleDirection()) dir="{{ $getScheduleDirection() }}" @endif
        role="group" aria-label="{{ $getLabel() }}">
        @if ($timezoneControl)
            <div class="fff-schedule-field__timezone">
                <label class="fff-schedule-field__timezone-label">{{ $labels['timezone'] }}</label>
                <x-filament-flex-fields::select-control :field="$timezoneControl" state-expression="scheduleState.timezone" on-change="values => updateTimezone(values[0])" disabled-expression="!isInteractive" :label="$labels['timezone']" :size="$size" :variant="$variant" :initial-value="$initialState['timezone'] ?? $config['defaultTimezone']" />
            </div>
        @endif
        <div class="fff-schedule-field__days">
            @foreach ($config['days'] as $day)
                @include('filament-flex-fields::forms.components.partials.schedule.day')
            @endforeach
        </div>
    </div>
</x-dynamic-component>
