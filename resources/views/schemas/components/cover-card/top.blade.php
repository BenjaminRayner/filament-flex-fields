@if ($cover->hasTop())
    <div class="fff-cover-card__top">
        @if ($cover->topTitle !== null)
            <span class="fff-cover-card__top-title">{{ $cover->topTitle }}</span>
        @endif
        @if ($cover->topDescription !== null)
            <span class="fff-cover-card__top-description">{{ $cover->topDescription }}</span>
        @endif
    </div>
@endif
