<span class="fff-hold-confirm-action__feedback" data-hold-feedback hidden>
    <span data-hold-state="pending" hidden>
        <x-filament::loading-indicator class="fff-confirm-morph__spinner" aria-hidden="true" />
        <span>{{ $labels['pending'] }}</span>
    </span>
    <span data-hold-state="done" hidden>
        @include('filament-flex-fields::actions.confirmation.check')
        <span>{{ $labels['done'] }}</span>
    </span>
    <span data-hold-state="error" hidden>
        <x-filament::icon icon="hugeicons-alert-circle" class="fff-confirm-morph__alert" aria-hidden="true" />
        <span>{{ $labels['error'] }}</span>
        <span class="fff-hold-confirm-action__retry">{{ $labels['retry'] }}</span>
    </span>
</span>
