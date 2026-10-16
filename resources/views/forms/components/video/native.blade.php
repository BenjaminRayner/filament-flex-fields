@if ($video->source !== null)
    <video class="fff-video-field__video" x-ref="video" src="{{ $video->source }}" x-bind:src="videoSrc || null"
        @if ($poster) poster="{{ $poster }}" @endif
        @if ($options['nativeControls'] && $options['showControls']) controls @endif
        @if ($options['loop']) loop @endif @if ($options['muted']) muted @endif
        @if ($options['playsInline']) playsinline @endif preload="metadata"
    ></video>
    @if ($showCustomControls)
        <video x-ref="previewVideo" hidden preload="none" muted playsinline tabindex="-1" aria-hidden="true"></video>
    @endif
    @if ($showPictureInPictureControl)
        <div class="fff-video-field__pip-placeholder" x-show="isPictureInPicture" x-cloak aria-hidden="true">
            @if ($poster)
                <img class="fff-video-field__pip-placeholder-image" src="{{ $poster }}" alt="" decoding="async" />
            @else
                <div class="fff-video-field__pip-placeholder-fallback"></div>
            @endif
        </div>
    @endif
@else
    <div class="fff-video-field__empty" aria-hidden="true">
        @if ($poster)
            <img class="fff-video-field__placeholder" src="{{ $poster }}" alt="" decoding="async" />
        @else
            <div class="fff-video-field__placeholder fff-video-field__placeholder--fallback">
                {{ \Filament\Support\generate_icon_html($field->getPlaceholderIcon(), size: \Filament\Support\Enums\IconSize::Large) }}
            </div>
        @endif
    </div>
@endif
