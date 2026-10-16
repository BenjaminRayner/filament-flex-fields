@php
    use Filament\Support\Enums\IconSize;
@endphp

@if ($showSettingsMenu && ($includeSettingsMenu ?? true))
    @include('filament-flex-fields::forms.components.partials.video-field-settings-menu', [
        'toolbarIconSize' => $toolbarIconSize,
    ])
@endif

@if ($showRemotePlaybackControl)
    <div x-show="remotePlaybackSupported" x-cloak class="fff-video-field__control-tip-wrap">
        <x-filament-flex-fields::video-field-control-tip :enabled="$showTooltips">
            <x-slot:tooltip>
                <span x-text="remotePlaybackTooltipLabel"></span>
            </x-slot:tooltip>
            <button
                type="button"
                class="fff-video-field__glass-btn fff-video-field__glass-btn--remote-playback"
                x-on:click="toggleRemotePlayback()"
                x-bind:disabled="! remotePlaybackSupported"
                x-bind:data-remote-playback-state="remotePlaybackActive ? 'connected' : (remotePlaybackConnecting ? 'connecting' : 'disconnected')"
                x-bind:aria-label="remotePlaybackAriaLabel"
            >
                <span x-show="remotePlaybackMode === 'airplay' && ! remotePlaybackActive" aria-hidden="true">
                    {{ \Filament\Support\generate_icon_html($field->getAirPlayIcon(), size: $toolbarIconSize) }}
                </span>
                <span x-show="remotePlaybackMode === 'cast' && ! remotePlaybackActive" style="display: none" aria-hidden="true">
                    {{ \Filament\Support\generate_icon_html($field->getCastIcon(), size: $toolbarIconSize) }}
                </span>
                <span x-show="remotePlaybackMode === 'airplay' && remotePlaybackActive" style="display: none" aria-hidden="true">
                    {{ \Filament\Support\generate_icon_html($field->getExitAirPlayIcon(), size: $toolbarIconSize) }}
                </span>
                <span x-show="remotePlaybackMode === 'cast' && remotePlaybackActive" style="display: none" aria-hidden="true">
                    {{ \Filament\Support\generate_icon_html($field->getExitCastIcon(), size: $toolbarIconSize) }}
                </span>
            </button>
        </x-filament-flex-fields::video-field-control-tip>
    </div>
@endif

@if ($showPictureInPictureControl)
    <x-filament-flex-fields::video-field-control-tip :enabled="$showTooltips" shortcut="i">
        <x-slot:tooltip>
            <span x-text="isPictureInPicture ? @js(__('filament-flex-fields::default.video.exit_picture_in_picture')) : @js(__('filament-flex-fields::default.video.picture_in_picture'))"></span>
        </x-slot:tooltip>
        <button
            type="button"
            class="fff-video-field__glass-btn"
            x-on:click="togglePictureInPicture()"
            x-bind:disabled="! pictureInPictureSupported"
            x-bind:aria-label="isPictureInPicture ? @js(__('filament-flex-fields::default.video.exit_picture_in_picture')) : @js(__('filament-flex-fields::default.video.picture_in_picture'))"
        >
            <span x-show="! isPictureInPicture" aria-hidden="true">
                {{ \Filament\Support\generate_icon_html($field->getPictureInPictureIcon(), size: $toolbarIconSize) }}
            </span>
            <span x-show="isPictureInPicture" style="display: none" aria-hidden="true">
                {{ \Filament\Support\generate_icon_html($field->getExitPictureInPictureIcon(), size: $toolbarIconSize) }}
            </span>
        </button>
    </x-filament-flex-fields::video-field-control-tip>
@endif

@if ($options['fullscreenable'])
    @include('filament-flex-fields::forms.components.video.fullscreen')
@endif
