<div class="fff-teleported-menu fff-dt-calendar" role="dialog" aria-label="{{ $getLabel() }}"
    dir="{{ $configuration['direction'] }}" data-fff-calendar-panel>
    <div class="fff-dt-calendar__header">
        <div class="fff-dt-calendar__title">
            <button type="button" data-command="months" aria-label="{{ $configuration['labels']['choose_month'] }}"></button>
            <button type="button" data-command="years" aria-label="{{ $configuration['labels']['choose_year'] }}"></button>
        </div>
        <div class="fff-dt-calendar__navigation">
            @if ($configuration['highlightToday'])
                <button type="button" data-command="today" class="fff-dt-calendar__today">{{ $configuration['labels']['today'] }}</button>
            @endif
            <button type="button" data-command="previous" class="fff-dt-calendar__nav" aria-label="{{ $configuration['labels']['previous'] }}">
                <x-filament::icon icon="hugeicons-arrow-left-01" />
            </button>
            <button type="button" data-command="next" class="fff-dt-calendar__nav" aria-label="{{ $configuration['labels']['next'] }}">
                <x-filament::icon icon="hugeicons-arrow-right-01" />
            </button>
        </div>
    </div>
    <div class="fff-dt-calendar__weekdays" aria-hidden="true" data-calendar-weekdays></div>
    <div class="fff-dt-calendar__viewport" data-calendar-viewport>
        <div class="fff-dt-calendar__content" data-calendar-content></div>
        <span class="fff-dt-calendar__selection" aria-hidden="true" data-calendar-selection></span>
    </div>
    @include('filament-flex-fields::forms.components.date-time.calendar.time-controls')
    <div class="fff-dt-calendar__footer" data-calendar-footer>
        <button type="button" data-command="clear">{{ $configuration['labels']['clear'] }}</button>
        <span data-calendar-summary></span>
    </div>
    <div class="fff-dt-calendar__status" role="status" aria-live="polite" hidden data-calendar-status></div>
    <button type="button" class="fff-dt-calendar__retry" hidden data-command="retry">{{ $configuration['labels']['retry'] }}</button>
    <span class="sr-only" aria-live="polite" data-calendar-announcement></span>
</div>
