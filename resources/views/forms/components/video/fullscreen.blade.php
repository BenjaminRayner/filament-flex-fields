<x-filament-flex-fields::video-field-control-tip :enabled="$showTooltips" shortcut="f">
        <x-slot:tooltip>
            <span x-text="isFullscreen ? @js(__('filament-flex-fields::default.video.exit_fullscreen')) : @js(__('filament-flex-fields::default.video.fullscreen'))"></span>
        </x-slot:tooltip>
        <button
            type="button"
            class="fff-video-field__glass-btn fff-video-field__glass-btn--fullscreen"
            x-on:click="toggleFullscreen()"
            x-bind:data-fullscreen="isFullscreen ? '' : null"
            x-bind:aria-label="isFullscreen ? @js(__('filament-flex-fields::default.video.exit_fullscreen')) : @js(__('filament-flex-fields::default.video.fullscreen'))"
        >
            <span x-show="! isFullscreen" aria-hidden="true">
                {{ \Filament\Support\generate_icon_html($field->getFullscreenIcon(), size: $toolbarIconSize) }}
            </span>
            <span x-show="isFullscreen" style="display: none" aria-hidden="true">
                {{ \Filament\Support\generate_icon_html($field->getExitFullscreenIcon(), size: $toolbarIconSize) }}
            </span>
        </button>
    </x-filament-flex-fields::video-field-control-tip>
