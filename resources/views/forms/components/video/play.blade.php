<button type="button" class="fff-video-field__glass-btn" x-on:click="togglePlay()" x-bind:aria-label="playLabel" @disabled($options['readOnly'])>
    <span class="fff-video-field__icon-play" x-show="! playing" aria-hidden="true">{{ \Filament\Support\generate_icon_html($field->getPlayIcon(), size: $toolbarIconSize) }}</span>
    <span class="fff-video-field__icon-pause" x-show="playing" style="display: none" aria-hidden="true">{{ \Filament\Support\generate_icon_html($field->getPauseIcon(), size: $toolbarIconSize) }}</span>
</button>
