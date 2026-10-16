<div class="fff-signature__pad" x-bind:class="{ 'has-guidelines': guidelinesEnabled, 'is-clearing': isClearing }" x-bind:style="padInlineBackgroundStyle">
    @if (! $fullscreenPad && $initialSvg)
        <img class="fff-signature__initial" src="data:image/svg+xml;base64,{{ base64_encode($initialSvg) }}" alt="{{ $getLabel() }}" x-show="!displayReady" />
    @endif
    <canvas x-ref="{{ $fullscreenPad ? 'fullscreenCanvas' : 'canvas' }}" class="fff-signature__canvas" role="img"
        aria-label="{{ __('filament-flex-fields::default.signature.placeholder') }}"
        x-on:pointerdown="onPointerDown($event)" x-on:pointermove="onPointerMove($event)" x-on:pointerup="onPointerUp($event)"
        x-on:pointercancel="onPointerCancel($event)" x-on:pointerleave="onPointerLeave($event)"></canvas>
    <div class="fff-signature__baseline" aria-hidden="true"><span>×</span><span class="fff-signature__signer">{{ $field->getSigner() }}</span></div>
    <p class="fff-signature__placeholder" x-show="!hasSignature && !isDrawing && !isClearing">{{ __('filament-flex-fields::default.signature.placeholder') }}</p>
    @if ($field->isTrackpadGlideEnabled() && !$isDisabled && !$isReadOnly)
        <button type="button" class="fff-signature__glide-pill" x-on:click.stop="toggleGlideFromPill()" x-bind:aria-pressed="glideArmed" x-bind:class="{ 'is-active': glideArmed }" x-text="glidePillText"></button>
    @endif
</div>
