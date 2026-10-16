@php($labels = $config['labels'])
<div class="fff-confirm-morph" data-morph-pill tabindex="-1" role="group" aria-label="{{ $config['prompt'] }}">
    <div class="fff-confirm-morph__face" data-face="idle">
        <span data-part>{!! $action->renderHoldConfirmTriggerHtml() !!}</span>
    </div>
    <div class="fff-confirm-morph__face" data-face="confirming" hidden>
        <span class="fff-confirm-morph__prompt" data-part title="{{ $config['prompt'] }}">{{ $config['prompt'] }}</span>
        <span data-part><button type="button" class="fff-confirm-morph__quiet" data-confirm-role="cancel">{{ $labels['cancel'] }}</button></span>
        <span data-part>
            <x-filament::button size="sm" :color="$action->getColor()" class="fff-confirm-morph__confirm" data-confirm-role="confirm"
                :wire:loading.attr="false"
                :aria-label="$config['mode'] === 'hold' ? $labels['hold'] : $labels['confirm']">
                <span class="fff-confirm-morph__answer">{{ $labels['confirm'] }}</span>
                <span class="fff-confirm-morph__fill" data-hold-overlay aria-hidden="true">{{ $labels['confirm'] }}</span>
            </x-filament::button>
        </span>
    </div>
    <div class="fff-confirm-morph__face" data-face="countdown" hidden>
        <span class="fff-confirm-morph__status" data-part>
            <svg viewBox="0 0 16 16" class="fff-confirm-morph__ring" fill="none" aria-hidden="true">
                <circle class="fff-confirm-morph__ring-track" cx="8" cy="8" r="6.25" stroke-width="1.75" />
                <circle class="fff-confirm-morph__ring-fill" cx="8" cy="8" r="6.25" stroke-width="1.75" stroke-linecap="round" pathLength="1" />
            </svg>
            <span class="fff-confirm-morph__status-text">{{ $labels['countdown'] }}
                <span class="fff-confirm-morph__seconds" data-countdown-number aria-hidden="true"><span>{{ $config['countdown'] }}</span></span>
            </span>
        </span>
        <span data-part><button type="button" class="fff-confirm-morph__quiet" data-confirm-role="cancel">{{ $labels['cancel'] }}</button></span>
    </div>
    <div class="fff-confirm-morph__face" data-face="pending" hidden>
        <span class="fff-confirm-morph__status" data-part>
            <x-filament::loading-indicator class="fff-confirm-morph__spinner" aria-hidden="true" />
            <span class="fff-confirm-morph__status-text" data-pending-label>{{ $labels['pending'] }}</span>
        </span>
    </div>
    <div class="fff-confirm-morph__face" data-face="done" hidden>
        <span class="fff-confirm-morph__status" data-part>
            @include('filament-flex-fields::actions.confirmation.check')
            <span class="fff-confirm-morph__status-text" data-done-label>{{ $labels['done'] }}</span>
        </span>
        @if ($config['hasUndo'])
            <span data-part><button type="button" class="fff-confirm-morph__quiet" data-confirm-role="undo">{{ $labels['undo'] }}</button></span>
        @endif
    </div>
    <div class="fff-confirm-morph__face" data-face="error" hidden>
        <span class="fff-confirm-morph__status" data-part>
            <x-filament::icon icon="hugeicons-alert-circle" class="fff-confirm-morph__alert" aria-hidden="true" />
            <span class="fff-confirm-morph__status-text">{{ $labels['error'] }}</span>
        </span>
        <span data-part><button type="button" class="fff-confirm-morph__quiet" data-confirm-role="retry">{{ $labels['retry'] }}</button></span>
    </div>
</div>
