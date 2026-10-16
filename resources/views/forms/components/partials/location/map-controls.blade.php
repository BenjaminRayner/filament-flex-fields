@php
    $controlGroups = \Bjanczak\FilamentFlexFields\Support\Location\Presentation\MapControlGroups::for($mapConfiguration, !$isDisabled && !$isReadOnly, $geometryMode);
@endphp
<div @class(['fff-location__controls', 'fff-map-controls', 'is-compact' => $mapConfiguration['compactControls']]) x-show="mapReady" x-cloak>
    @foreach ($controlGroups as $buttons)
        <div @class(['fff-map-controls__capsule', 'fff-map-controls__capsule--vertical' => count($buttons) > 1]) role="group">
            @foreach ($buttons as $button)
                <button type="button" class="fff-map-controls__button" data-command="{{ $button['command'] }}"
                    x-on:click="{{ $button['click'] }}" title="{{ __('filament-flex-fields::location.'.$button['label']) }}" aria-label="{{ __('filament-flex-fields::location.'.$button['label']) }}"
                    @if ($button['disabled']) x-bind:disabled="{{ $button['disabled'] }}" @endif
                    @if ($button['command'] === 'follow') x-bind:aria-pressed="trackFollowing" @endif
                    @if ($button['command'] === 'playback') x-bind:aria-pressed="trackPlaying" @endif>
                    @if ($button['command'] === 'playback')
                        <span x-show="!trackPlaying"><x-filament-flex-fields::map-icon name="play" /></span>
                        <span x-show="trackPlaying" x-cloak><x-filament-flex-fields::map-icon name="pause" /></span>
                    @else
                        <x-filament-flex-fields::map-icon :name="$button['command']" />
                    @endif
                </button>
            @endforeach
        </div>
    @endforeach
</div>
