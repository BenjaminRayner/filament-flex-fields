@if ($configuration['withTime'])
    <div class="fff-dt-clock" data-calendar-time>
        @foreach ($configuration['mode'] === 'dateRange' ? ['start_time', 'end_time'] : ['time'] as $index => $label)
            <div class="fff-dt-clock__row" data-clock-row="{{ $index }}">
                <span class="fff-dt-clock__label">{{ $configuration['labels'][$label] }}</span>
                <div class="fff-flex-text-input fff-flex-text-input--sm fff-flex-text-input--primary fff-rounding-xl">
                    <x-filament-flex-fields::text-input.shell :prefix-icon="\Bjanczak\FilamentFlexFields\Support\FlexIcon::Clock" :actions-framed="false">
                        <button type="button" class="fff-dt-clock__trigger" data-clock-trigger aria-haspopup="listbox" aria-expanded="false" aria-label="{{ $configuration['labels'][$label] }}"></button>
                        <x-slot:actions><span class="fff-flex-text-input__action-item"><span class="fff-flex-text-input__action-btn" aria-hidden="true"><x-filament::icon :icon="\Bjanczak\FilamentFlexFields\Support\FlexIcon::CircleChevronDown" stroke-width="2" class="fff-flex-text-input__action-icon" /></span></span></x-slot:actions>
                    </x-filament-flex-fields::text-input.shell>
                </div>
                @if ($configuration['timeGranularity'] === 'second')
                    <button type="button" class="fff-dt-clock__seconds" data-clock-seconds aria-label="{{ $configuration['labels']['seconds'] }}" aria-haspopup="listbox"></button>
                @endif
            </div>
        @endforeach
        @if (! $configuration['hideTimeZone'])
            <span class="fff-dt-clock__zone" title="{{ $configuration['timeZone'] }}">{{ $configuration['timeZone'] }}</span>
        @endif
    </div>
@endif
