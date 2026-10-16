@php
    use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
    use Bjanczak\FilamentFlexFields\Support\Confirmation\ConfirmationRenderState;
    use Filament\Support\Facades\FilamentAsset;

    $confirmation = ConfirmationRenderState::from($action);
    $config = $confirmation->configuration;
    $alpineSrc = FilamentAsset::getAlpineComponentSrc('hold-confirm-action', FilamentFlexFieldsPlugin::PACKAGE_NAME);
@endphp

@include('filament-flex-fields::partials.load-stylesheet', [
    'component' => 'hold-confirm-action',
    'livewireKey' => $confirmation->consumer,
    'assetSchemaComponent' => $action->getSchemaComponent(),
])

<div class="fff-hold-confirm-action-host" wire:key="{{ $confirmation->key }}"
    wire:ignore.self data-fff-hold-confirm data-variant="{{ $config['variant'] }}" data-state="idle"
    @if ($action->isHoldConfirmAuditReasonRequired()) data-fff-audit-reason-required="true" @endif
    x-fff-load x-fff-load-src="{{ $alpineSrc }}"
    x-data="holdConfirmActionFormComponent({
        ...@js($config),
        execute: async (operation) => {
            @if (filled($config['url']))
                if (operation !== 'confirm') throw new Error('Unsupported navigation operation');
                @if ($config['newTab'])
                    window.open(@js($config['url']), '_blank', 'noopener,noreferrer');
                @else
                    window.location.assign(@js($config['url']));
                @endif
                return { fffConfirmation: true, action: @js($config['actionName']), operation, success: true };
            @else
                return await $wire.mountAction(@js($config['actionName']), {
                    ...@js($config['arguments']), _fff_confirmation_operation: operation
                }, @js($config['context']));
            @endif
        },
    })">
    <div class="fff-confirmation__ui" wire:ignore>
        @if ($config['variant'] === 'morph')
            @include('filament-flex-fields::actions.confirmation.morph')
        @else
            {!! $action->renderHoldConfirmTriggerHtml() !!}
        @endif
        <span class="fff-confirmation__announcement" role="status" aria-live="polite" aria-atomic="true"></span>
    </div>
</div>
