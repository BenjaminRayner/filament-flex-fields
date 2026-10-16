<div class="fff-teleported-menu fff-dt-calendar fff-dt-range" role="dialog" aria-label="{{ $getLabel() }}"
    dir="{{ $configuration['direction'] }}" data-fff-calendar-panel>
    <div class="fff-dt-range__body">
        <div class="fff-dt-range__presets" data-range-presets></div>
        <div class="fff-dt-range__calendars">
            <button type="button" data-command="previous" class="fff-dt-calendar__nav fff-dt-range__previous" aria-label="{{ $configuration['labels']['previous'] }}"><x-filament::icon icon="hugeicons-arrow-left-01" /></button>
            <button type="button" data-command="next" class="fff-dt-calendar__nav fff-dt-range__next" aria-label="{{ $configuration['labels']['next'] }}"><x-filament::icon icon="hugeicons-arrow-right-01" /></button>
            <div class="fff-dt-calendar__viewport" data-range-viewport>
                <div class="fff-dt-calendar__content" data-range-content></div>
            </div>
        </div>
    </div>
    @include('filament-flex-fields::forms.components.date-time.calendar.time-controls')
    <div class="fff-dt-range__footer">
        <div class="fff-dt-range__summary"><span data-range-summary></span><small data-range-count></small></div>
        <div class="fff-dt-range__actions">
            @if ($configuration['clearable'])
                <button type="button" data-command="clear">{{ $configuration['labels']['clear'] }}</button>
            @endif
            <button type="button" data-command="cancel">{{ $configuration['labels']['cancel'] }}</button>
            <button type="button" class="fff-dt-range__apply" data-command="apply">{{ $configuration['labels']['apply'] }}</button>
        </div>
    </div>
    <div class="fff-dt-calendar__status" hidden role="status" data-calendar-status></div>
    <button type="button" class="fff-dt-calendar__retry" hidden data-command="retry">{{ $configuration['labels']['retry'] }}</button>
    <span class="sr-only" aria-live="polite" data-calendar-announcement></span>
</div>
