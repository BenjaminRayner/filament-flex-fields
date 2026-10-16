<div class="fff-video-field__toolbar fff-video-field__toolbar--compact">
    <div class="fff-video-field__compact-primary">
        <div class="fff-video-field__toolbar-start fff-video-field__toolbar-start--compact">
            @include('filament-flex-fields::forms.components.video.play')
            @if ($options['volumeControl'])
                @include('filament-flex-fields::forms.components.partials.video-field-volume-control', ['iconSize' => $toolbarIconSize])
            @endif
        </div>
        <div class="fff-video-field__time-controls fff-video-field__time-controls--compact">
            <span class="fff-video-field__time-current" x-text="currentLabel" aria-live="off">0:00</span>
            <div class="fff-video-field__progress-block fff-video-field__progress-block--compact">
                @include('filament-flex-fields::forms.components.partials.video-field-progress')
            </div>
            <button type="button" class="fff-video-field__time-remaining" x-on:click.stop="toggleTimeDisplay()" x-text="endTimeLabel" x-bind:aria-label="endTimeToggleLabel" aria-live="off">-0:00</button>
        </div>
        @if ($showSettingsMenu)
            @include('filament-flex-fields::forms.components.partials.video-field-settings-menu', ['toolbarIconSize' => $toolbarIconSize])
        @endif
    </div>
    <div class="fff-video-field__toolbar-end fff-video-field__toolbar-end--compact">
        @include('filament-flex-fields::forms.components.partials.video-field-toolbar-end', ['includeSettingsMenu' => false])
    </div>
</div>
