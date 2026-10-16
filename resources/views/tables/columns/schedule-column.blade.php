@php
    $schedule = $getScheduleDisplay();
    $activeDays = array_filter($schedule['days'], fn (array $day): bool => $day['active']);
@endphp
<div class="fff-schedule-summary" @if ($getScheduleDirection()) dir="{{ $getScheduleDirection() }}" @endif role="group" aria-label="{{ $getLabel() }}">
    @forelse ($activeDays as $day)
        @php($tooltip = $day['tooltip'].($schedule['timezone'] ? ' · '.$schedule['timezone'] : ''))
        <span class="fff-schedule-summary__day" tabindex="0" title="{{ $tooltip }}"
            aria-label="{{ $tooltip }}" x-data="{}"
            x-tooltip="{ content: @js($tooltip), allowHTML: false, theme: 'fff', arrow: false, maxWidth: 320 }">
            {{ $day['short'] }}
        </span>
    @empty
        <span class="fff-schedule-summary__empty">{{ $schedule['closed'] }}</span>
    @endforelse
</div>
