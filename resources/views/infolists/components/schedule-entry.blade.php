@php($schedule = $getScheduleDisplay())
<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    <div class="fff-schedule-entry" @if ($getScheduleDirection()) dir="{{ $getScheduleDirection() }}" @endif>
        @if ($getScheduleHeading() || $getScheduleDescription())
            <div class="fff-schedule-entry__heading">
                {{ \Filament\Support\generate_icon_html('hugeicons-clock-01', attributes: new \Illuminate\View\ComponentAttributeBag(['class' => 'fff-schedule-entry__icon'])) }}
                <div>
                    @if ($getScheduleHeading())<strong>{{ $getScheduleHeading() }}</strong>@endif
                    @if ($getScheduleDescription())<p>{{ $getScheduleDescription() }}</p>@endif
                </div>
            </div>
        @endif
        <div class="fff-schedule-entry__body">
            @if ($shouldShowTimezone() && $schedule['timezone'])
                <div class="fff-schedule-entry__timezone">
                    <span>{{ \Bjanczak\FilamentFlexFields\Support\Translations::get('filament-flex-fields::default.schedule.timezone', locale: $getScheduleDisplayLocale()) }}</span>
                    <bdi>{{ $schedule['timezone'] }}</bdi>
                </div>
            @endif
            <dl class="fff-schedule-entry__days">
                @foreach ($schedule['days'] as $day)
                    @continue(! $day['active'] && ! $shouldShowClosedDays())
                    <div @class(['fff-schedule-entry__day', 'is-closed' => ! $day['active']])>
                        <dt>{{ $day['label'] }}</dt>
                        <dd><bdi>{{ $day['hours'] }}</bdi></dd>
                    </div>
                @endforeach
            </dl>
            @if (! $schedule['active'] && ! $shouldShowClosedDays())
                <span class="fff-schedule-summary__empty">{{ $schedule['closed'] }}</span>
            @endif
        </div>
    </div>
</x-dynamic-component>
