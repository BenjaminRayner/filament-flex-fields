@php
    $data = $getPresentation();
    $progress = $data['progress'];
    $percentage = $data['percentage'];
    $segments = $data['segments'];
    $segmentCount = count($segments);
    $isIndeterminate = $data['indeterminate'];
    $usesAutoPillCount = $data['pills'] && $data['pillCount'] === null;
    $pillCount = $data['pillCount'];
    $activePillCount = $data['activePills'];
    $hasDashedRemainder = $data['dashed'];
    $markers = filled($data['startMarker']) || filled($data['endMarker']) || filled($data['currentMarker']);
    $colorToken = $getColorToken();
    $markerIconSize = match ($data['size']) { 'sm' => \Filament\Support\Enums\IconSize::ExtraSmall, 'lg' => \Filament\Support\Enums\IconSize::Medium, default => \Filament\Support\Enums\IconSize::Small };
    $segmentIconSize = $data['size'] === 'lg' ? \Filament\Support\Enums\IconSize::Small : \Filament\Support\Enums\IconSize::ExtraSmall;
    $inlineStyles = ['--fff-progress-fill-duration: '.($shouldAnimateFill() ? $getAnimationDuration() : 0).'ms', '--fff-progress-bar-segment-count: '.max(1, $segmentCount)];
    if ($accent = $getAccentCssColor()) { $inlineStyles[] = '--fff-progress-bar-accent: '.$accent; }
@endphp
@include('filament-flex-fields::partials.load-stylesheet', ['component' => 'progress-bar', 'livewireKey' => $getLivewireKey()])
<div {{ $attributes->merge([
    'id' => $getId(), 'wire:key' => $getLivewireKey(), 'role' => 'progressbar',
    'aria-label' => $data['label'] ?? __('filament-flex-fields::progress.label'),
    'aria-valuemin' => $isIndeterminate ? null : 0, 'aria-valuemax' => $isIndeterminate ? null : $progress->max,
    'aria-valuenow' => $isIndeterminate ? null : $progress->current,
    'aria-valuetext' => $isIndeterminate ? null : $data['value'], 'aria-busy' => $isIndeterminate ? 'true' : null,
])->merge($getExtraAttributes())->class([
    'fff-progress-bar', 'fff-progress-bar--'.$data['size'], 'fi-color-'.$colorToken => filled($colorToken),
    'has-custom-accent' => $usesCustomAccentColor(), 'is-fill-static' => ! $shouldAnimateFill(),
    'is-indeterminate' => $isIndeterminate, 'has-segments' => $segmentCount > 0,
    'has-segment-icons' => $data['hasIcons'], 'has-markers' => $markers,
    'has-dashed-remainder' => $hasDashedRemainder, 'is-route' => $markers && $hasDashedRemainder,
    'is-pills' => $data['pills'], 'has-shell' => $data['shell'], 'has-card-chrome' => $data['shell'],
    'has-value-badge' => $data['badge'],
]) }} data-slot="progress-bar" style="{{ implode('; ', $inlineStyles) }}">
    @if ($data['shell'])<div class="fff-progress-bar__card">@endif
        @if (filled($data['label']) || ($data['showValue'] && ($data['shell'] ? $data['badge'] : $segmentCount === 0 && ! $data['pills'])))
            <div class="{{ $data['shell'] ? 'fff-progress-bar__card-header' : 'fff-progress-bar__header' }}">
                @if (filled($data['label']))<span class="{{ $data['shell'] ? 'fff-progress-bar__card-heading' : 'fff-progress-bar__label' }}">{{ $data['label'] }}</span>@endif
                @if ($data['showValue'] && (! $data['shell'] || $data['badge']))<span class="{{ $data['shell'] ? 'fff-progress-bar__value-badge' : 'fff-progress-bar__value' }}">{{ $data['value'] }}</span>@endif
            </div>
        @endif
        @if ($data['shell'] && filled($data['description']))<p class="fff-progress-bar__card-description">{{ $data['description'] }}</p>@endif
        @if ($data['pills'])
            @include('filament-flex-fields::schemas.components.partials.progress-bar-pills')
        @elseif ($segmentCount > 0)
            @include('filament-flex-fields::schemas.components.partials.progress-bar-segments')
        @else
            @include('filament-flex-fields::schemas.components.partials.progress-bar-track')
        @endif
        @if ($data['shell'] && filled($data['footer']))<div class="fff-progress-bar__card-action">{{ $data['footer'] }}</div>@endif
    @if ($data['shell'])</div>@endif
</div>
