<div class="fff-icon-select__skeleton fff-icon-select__skeleton--next" aria-hidden="true">
    @for ($cell = 0; $cell < ($iconLayout === 'list' ? 1 : $iconColumns); $cell++)
        <div class="fff-icon-select__skeleton-cell">
            <span class="fff-icon-select__skeleton-glyph"></span>
            @if ($iconLayout !== 'icons')
                <span class="fff-icon-select__skeleton-label"></span>
            @endif
        </div>
    @endfor
</div>
