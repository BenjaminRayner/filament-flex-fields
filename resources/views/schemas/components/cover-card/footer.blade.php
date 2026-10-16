@if ($cover->hasFooter())
    <div @class([
        'fff-cover-card__footer',
        'fff-cover-card__footer--action-only' => ! $cover->hasFooterCopy() && $cover->footerAction !== null,
    ])>
        @if ($cover->hasFooterCopy())
            <div class="fff-cover-card__footer-copy">
                @if ($cover->footerTitle !== null)
                    <span class="fff-cover-card__footer-title">{{ $cover->footerTitle }}</span>
                @endif
                @if ($cover->footerDescription !== null)
                    <span class="fff-cover-card__footer-description">{{ $cover->footerDescription }}</span>
                @endif
            </div>
        @endif
        @if ($cover->footerAction !== null)
            @php
                $footerAction = $cover->footerAction;
                if (! $footerAction->hasLivewireTarget() && ! $footerAction->canSubmitForm()) {
                    $footerAction = clone $footerAction;
                    $footerAction->livewireTarget($footerAction->getLivewireClickHandler());
                }
            @endphp
            <div class="fff-cover-card__footer-action fi-fixed-positioning-context">
                {{ $footerAction }}
            </div>
        @endif
    </div>
@endif
