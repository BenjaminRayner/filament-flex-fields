@props(['grid' => false])
<div class="fff-matrix-choice__frame">
    <div class="fff-matrix-choice__header" @if ($grid ?? false) role="row" @endif>
        <div class="fff-matrix-choice__corner" @if ($grid ?? false) role="columnheader" aria-hidden="true" @endif></div>
        {{ $header }}
    </div>
    <div class="fff-matrix-choice__body">{{ $slot }}</div>
</div>
