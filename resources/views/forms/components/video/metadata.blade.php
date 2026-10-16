@if ($hasMetadata)
    <div class="fff-video-field__meta">
        @if ($subtitle) <span class="fff-video-field__meta-kicker">{{ $subtitle }}</span> @endif
        @if ($title) <span class="fff-video-field__meta-title">{{ $title }}</span> @endif
    </div>
@endif
