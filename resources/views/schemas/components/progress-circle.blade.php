@php
    $data = $getPresentation();
@endphp
@if ($data['variant'] === 'breakdown')
    @include('filament-flex-fields::schemas.components.partials.progress-circle-breakdown')
@else
@php
    $metrics = $data['metrics'];
    $progress = $data['progress'];
    $contentLayout = $data['layout'];
    $variant = $data['variant'];
    $hasBelowLabel = $data['below'];
    [$gradientFrom, $gradientTo] = $data['gradient'];
    [$trackGradientFrom, $trackGradientTo] = $data['trackGradient'];
    $hasGradient = $gradientFrom !== null && $gradientTo !== null;
    $hasTrackGradient = $trackGradientFrom !== null && $trackGradientTo !== null;
    $hasCardChrome = $data['shell'] || filled($data['heading']) || filled($data['description']) || filled($data['footer']);
    $colorToken = $getColorToken();
    $pausedIconSize = match ($data['size']) {
        'xs' => \Filament\Support\Enums\IconSize::Small,
        'sm' => \Filament\Support\Enums\IconSize::Medium,
        'lg' => \Filament\Support\Enums\IconSize::ExtraLarge,
        default => \Filament\Support\Enums\IconSize::Large,
    };
    $inlineStyles = ['--fff-progress-fill-duration: '.($shouldAnimateFill() ? $getAnimationDuration() : 0).'ms'];
    if ($accent = $getAccentCssColor()) { $inlineStyles[] = '--fff-progress-circle-accent: '.$accent; }
@endphp
@include('filament-flex-fields::partials.load-stylesheet', ['component' => 'progress-circle', 'livewireKey' => $getLivewireKey()])
<div {{ $attributes->merge([
    'id' => $getId(), 'wire:key' => $getLivewireKey(), 'role' => 'progressbar',
    'aria-label' => $data['accessibleLabel'], 'aria-valuemin' => 0,
    'aria-valuemax' => $progress->max, 'aria-valuenow' => $progress->current,
    'aria-valuetext' => ($data['paused'] ? __('filament-flex-fields::progress.paused').': ' : '').$data['value'],
])->merge($getExtraAttributes())->class([
    'fff-progress-circle', 'fff-progress-circle--'.$data['size'], 'fff-progress-circle--'.$variant,
    'fff-progress-circle--layout-'.$contentLayout, 'fi-color-'.$colorToken => filled($colorToken),
    'has-custom-accent' => $usesCustomAccentColor(), 'is-fill-static' => ! $shouldAnimateFill(),
    'has-gradient' => $hasGradient, 'has-track-gradient' => $hasTrackGradient,
    'has-gap-arc' => $variant === 'circle' && $data['gap'] > 0, 'has-below-label' => $hasBelowLabel,
    'is-paused' => $data['paused'], 'has-shell' => $data['shell'], 'has-card-chrome' => $hasCardChrome,
]) }} data-slot="progress-circle" style="{{ implode('; ', $inlineStyles) }}">
    @if ($hasCardChrome)
        <div class="fff-progress-circle__card">
            @if (filled($data['heading']))<div class="fff-progress-circle__card-heading">{{ $data['heading'] }}</div>@endif
            @if (filled($data['description']))<div class="fff-progress-circle__card-description">{{ $data['description'] }}</div>@endif
            <div class="fff-progress-circle__body">@include('filament-flex-fields::schemas.components.partials.progress-circle-frame')</div>
            @if (filled($data['footer']))<div class="fff-progress-circle__card-footer">{{ $data['footer'] }}</div>@endif
        </div>
    @else
        @include('filament-flex-fields::schemas.components.partials.progress-circle-frame')
    @endif
</div>

@endif
