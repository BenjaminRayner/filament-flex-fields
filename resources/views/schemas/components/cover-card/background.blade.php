<div class="fff-cover-card__background"
    @if ($cover->backgroundStyles !== []) style="{{ implode('; ', $cover->backgroundStyles) }}" @endif
    aria-hidden="true"
></div>

@if (! $cover->contentOverlays)
    <div class="fff-cover-card__scrim" aria-hidden="true"></div>
@else
    @if ($cover->hasTop())
        <div class="fff-cover-card__overlay fff-cover-card__overlay--top"
            @if ($cover->topOverlay !== null) style="--fff-cover-card-top-overlay: {{ $cover->topOverlay }}" @endif
            aria-hidden="true"
        ></div>
    @endif
    @if ($cover->hasFooter())
        <div class="fff-cover-card__overlay fff-cover-card__overlay--bottom"
            @if ($cover->footerOverlay !== null) style="--fff-cover-card-footer-overlay: {{ $cover->footerOverlay }}" @endif
            aria-hidden="true"
        ></div>
    @endif
@endif
