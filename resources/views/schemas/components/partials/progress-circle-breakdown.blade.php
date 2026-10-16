@php
    $interactive = $data['interactive'];
    $interaction = [
        'segments' => array_map(static fn (array $segment): array => ['value' => (int) round($segment['ratio'] * 100).'%', 'label' => $segment['label']], $data['breakdown']->segments),
        'initialIndex' => $data['breakdown']->activeIndex, 'initialValue' => $data['value'], 'initialLabel' => $data['label'] ?? '',
    ];
@endphp
@include('filament-flex-fields::partials.load-stylesheet', ['component' => 'progress-circle', 'livewireKey' => $getLivewireKey(), 'capabilities' => $interactive ? ['interactive-breakdown'] : []])
<div {{ $attributes->merge(['id' => $getId(), 'wire:key' => $getLivewireKey().'-'.($interactive ? hash('xxh128', json_encode($interaction, JSON_THROW_ON_ERROR)) : 'static')])->merge($getExtraAttributes())->class([
    'fff-progress-circle', 'fff-progress-circle--breakdown', 'fff-progress-circle--'.$data['size'],
    'has-shell' => $data['shell'],
]) }}
    @if ($interactive)
        x-fff-load x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('progress-circle-breakdown', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
        x-data="progressCircleBreakdownComponent(@js($interaction))"
        @pointerleave="leave()" @keydown.escape="reset(); $event.target.blur()"
    @endif
    data-slot="progress-circle" aria-label="{{ $data['accessibleLabel'] }}">
    @if (filled($data['heading']) || filled($data['summaryValue']) || filled($data['periodLabel']))
        <div class="fff-progress-circle__breakdown-header">
            <div class="fff-progress-circle__breakdown-summary">
                @if (filled($data['heading']))<span class="fff-progress-circle__breakdown-heading">{{ $data['heading'] }}</span>@endif
                <div class="fff-progress-circle__breakdown-total-row">
                    @if (filled($data['summaryValue']))<span class="fff-progress-circle__breakdown-total">{{ $data['summaryValue'] }}</span>@endif
                    @if (filled($data['changeLabel']))<span class="fff-progress-circle__breakdown-change">{{ $data['changeLabel'] }}</span>@endif
                </div>
            </div>
            @if (filled($data['periodLabel']))
                <span class="fff-progress-circle__breakdown-period">
                    <x-filament::icon icon="hugeicons-calendar-01" class="fff-progress-circle__breakdown-period-icon" />
                    {{ $data['periodLabel'] }}
                </span>
            @endif
        </div>
    @endif
    @if (filled($data['description']))<p class="fff-progress-circle__card-description">{{ $data['description'] }}</p>@endif
    <div class="fff-progress-circle__breakdown-gauge">
        <svg class="fff-progress-circle__breakdown-svg" viewBox="0 0 240 142" role="{{ $interactive ? 'group' : 'img' }}" aria-label="{{ $data['accessibleLabel'] }}" focusable="false">
            <title>{{ $data['accessibleLabel'] }}</title>
            <path class="fff-progress-circle__breakdown-track" d="{{ \Bjanczak\FilamentFlexFields\Support\Progress\CircleBreakdown::sector(0, 180) }}" />
            @foreach ($data['breakdown']->segments as $index => $segment)
                @if ($segment['path'] !== '')
                    <path class="fff-progress-circle__breakdown-segment" d="{{ $segment['path'] }}" fill="{{ $segment['color'] }}"
                        @if ($interactive)
                            role="button" tabindex="{{ $data['legend'] ? -1 : 0 }}" aria-label="{{ $segment['label'] }}: {{ (int) round($segment['ratio'] * 100) }}%"
                            @pointerenter="preview({{ $index }}, $event)" @pointerleave="leave()" @click="select({{ $index }})"
                            @focus="focus({{ $index }})" @blur="focus(null)"
                            @keydown.enter.prevent="select({{ $index }})" @keydown.space.prevent="select({{ $index }})"
                        @endif
                    ><title>{{ $segment['label'] }}: {{ $segment['displayValue'] }}</title></path>
                @endif
            @endforeach
        </svg>
        <div class="fff-progress-circle__breakdown-center" @if ($interactive) aria-live="polite" aria-atomic="true" @endif>
            @if ($data['showValue'])<span class="fff-progress-circle__breakdown-value" @if ($interactive) x-text="value" @endif>{{ $data['value'] }}</span>@endif
            @if (filled($data['label']))<span class="fff-progress-circle__breakdown-label" @if ($interactive) x-text="label" @endif>{{ $data['label'] }}</span>@endif
        </div>
    </div>
    @if ($data['legend'])
        <ul class="fff-progress-circle__legend" aria-label="{{ $data['accessibleLabel'] }}">
            @foreach ($data['breakdown']->segments as $index => $segment)
                <li class="fff-progress-circle__legend-item">
                    @if ($interactive)
                        <button type="button" class="fff-progress-circle__legend-button"
                            @pointerenter="preview({{ $index }}, $event)" @pointerleave="leave()" @click="select({{ $index }})"
                            @focus="focus({{ $index }})" @blur="focus(null)"
                            :aria-pressed="currentIndex === {{ $index }}">
                    @endif
                    <span class="fff-progress-circle__legend-swatch" style="background:{{ $segment['color'] }}" aria-hidden="true"></span>
                    <span class="fff-progress-circle__legend-label">{{ $segment['label'] }}</span>
                    <span class="fff-progress-circle__legend-value">{{ $segment['displayValue'] }}</span>
                    @if ($interactive)</button>@endif
                </li>
            @endforeach
        </ul>
    @endif
    @if (filled($data['footer']))<div class="fff-progress-circle__card-footer">{{ $data['footer'] }}</div>@endif
</div>
