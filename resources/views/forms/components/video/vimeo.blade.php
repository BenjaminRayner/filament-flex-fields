<div class="fff-video-field__frame" @if ($video->ratio) style="aspect-ratio: {{ $video->ratio }};" @endif>
    <iframe class="fff-video-field__vimeo-iframe" src="{{ $video->embedUrl }}" title="{{ $title ?? $getLabel() }}" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen loading="lazy"></iframe>
</div>
