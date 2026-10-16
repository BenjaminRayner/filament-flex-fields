<div class="fff-matrix-choice__row-label" @if ($isChoiceGrid ?? false) role="rowheader" @endif>
    <span class="fff-matrix-choice__row-title">{{ $row['label'] }}</span>
    @if (filled($row['description']))
        <span class="fff-matrix-choice__row-description">{{ $row['description'] }}</span>
    @endif
</div>
