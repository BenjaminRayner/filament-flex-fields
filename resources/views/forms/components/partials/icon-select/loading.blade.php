<div class="fff-icon-select__skeleton" aria-hidden="true">
    @for ($cell = 0; $cell < min($iconPageSize, $iconLayout === 'list' ? 5 : $iconColumns * 5); $cell++)
        <div class="fff-icon-select__skeleton-cell">
            <span class="fff-icon-select__skeleton-glyph"></span>
            @if ($iconLayout !== 'icons')
                <span class="fff-icon-select__skeleton-label"></span>
            @endif
        </div>
    @endfor
</div>
