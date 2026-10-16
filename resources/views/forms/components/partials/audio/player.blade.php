<div class="fff-audio-field__player">
    <div class="fff-audio-field__pill">
        <button type="button" class="fff-audio-field__play" x-on:click="togglePlay()"
            aria-label="{{ __('filament-flex-fields::default.audio.play') }}"
            x-bind:aria-label="playing ? labels.pause : labels.play" x-bind:disabled="!canInteract">
            <span class="fff-audio-field__icon-play" x-show="!playing" aria-hidden="true">
                {{ \Filament\Support\generate_icon_html($field->getPlayIcon(), size: \Filament\Support\Enums\IconSize::ExtraSmall) }}
            </span>
            <span class="fff-audio-field__icon-pause" x-show="playing" x-cloak aria-hidden="true">
                {{ \Filament\Support\generate_icon_html($field->getPauseIcon(), size: \Filament\Support\Enums\IconSize::ExtraSmall) }}
            </span>
        </button>
        <div class="fff-audio-field__waveform" x-ref="waveform" role="slider" tabindex="0"
            aria-label="{{ __('filament-flex-fields::default.audio.progress') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"
            x-bind:aria-valuenow="Math.round(progressRatio * 100)" x-bind:aria-disabled="!canInteract"
            x-bind:class="{ 'is-disabled': !canInteract }" x-on:pointerdown="onWaveformPointerDown($event)" x-on:keydown="onWaveformKeyDown($event)"
            style="--fff-audio-progress:0">
            <div class="fff-audio-field__waveform-skeleton" aria-hidden="true">
                @for ($index = 0; $index < 40; $index++)
                    <span class="fff-audio-field__skeleton-bar" style="--fff-audio-skeleton-i:{{ $index }}"></span>
                @endfor
                <span class="fff-audio-field__waveform-skeleton-shimmer"></span>
            </div>
            @foreach (['', ' fff-audio-field__bars--played'] as $layer)
                <div class="fff-audio-field__bars{{ $layer }}" aria-hidden="true">
                    <template x-for="(peak,index) in displayWaveform" :key="index">
                        <span class="fff-audio-field__bar {{ $layer !== '' ? 'fff-audio-field__bar--accent' : '' }}"
                            x-bind:style="'height:'+peak+'%;--fff-audio-bar-i:'+index"></span>
                    </template>
                </div>
            @endforeach
            <span class="fff-audio-field__playhead" aria-hidden="true" x-bind:class="{ 'is-visible':playheadVisible }"></span>
        </div>
        <span class="fff-audio-field__time" x-text="timeLabel">0:00</span>
    </div>
    <audio x-ref="audio" class="fff-audio-field__audio" preload="metadata" @if ($loop ?? false) loop @endif></audio>
</div>
