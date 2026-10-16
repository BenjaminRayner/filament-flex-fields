@php
    use Filament\Support\Enums\IconSize;
    use Filament\Forms\Components\TextInput\Actions\CopyAction;
    use Filament\Forms\Components\TextInput\Actions\ShowPasswordAction;
    use Filament\Forms\Components\TextInput\Actions\HidePasswordAction;
@endphp
@foreach ($suffixActions as $action)
    @if ($action instanceof CopyAction)
        @php
    $action->alpineClickHandler('copyInputValue()');
@endphp
        <div class="fff-flex-text-input__action-item fff-flex-text-input__copy"
            x-bind:class="{ 'is-copied': copyFeedback }">
            {{ $action }}
            <span class="fff-flex-text-input__copy-success" x-show="copyFeedback" style="display:none" aria-hidden="true">
                {{ \Filament\Support\generate_icon_html($getCopySuccessIcon(), size: IconSize::Small) }}
            </span>
        </div>
    @elseif (($action instanceof ShowPasswordAction || $action instanceof HidePasswordAction) && $options['revealMode'] === 'hold')
        @if ($action instanceof ShowPasswordAction)
            @php
                // Remove the native toggle-only visibility expression. Merging
                // null into its attribute bag retains it and hides a held trigger.
                $holdAttributes = $action->getExtraAttributes();
                unset($holdAttributes['x-show'], $holdAttributes['x-cloak']);
                $action->alpineClickHandler('$event.preventDefault()')->extraAttributes(array_replace($holdAttributes, [
                    'x-on:pointerdown.prevent' => 'revealPassword()', 'x-on:pointerup' => 'concealPassword()',
                    'x-on:pointerleave' => 'concealPassword()', 'x-on:pointercancel' => 'concealPassword()',
                    'x-on:keydown.space.prevent' => 'revealPassword()', 'x-on:keyup.space' => 'concealPassword()',
                    'x-on:keydown.enter.prevent' => 'revealPassword()', 'x-on:keyup.enter' => 'concealPassword()',
                    'x-on:blur' => 'concealPassword()',
                ]));
            @endphp
            <div class="fff-flex-text-input__action-item fff-flex-text-input__suffix-action">{{ $action }}</div>
        @endif
    @elseif ($action instanceof ShowPasswordAction)
        @php
    $action->alpineClickHandler('revealPassword()');
@endphp
        <div class="fff-flex-text-input__action-item fff-flex-text-input__suffix-action">
            {{ $action }}
            @foreach ($suffixActions as $passwordAction)
                @if ($passwordAction instanceof HidePasswordAction)
                    @php
    $passwordAction->alpineClickHandler('concealPassword()');
@endphp
                    {{ $passwordAction }}
                @endif
            @endforeach
        </div>
    @elseif ($action instanceof HidePasswordAction)
        {{-- Both native actions share one toolbar position. --}}
    @else
        <div class="fff-flex-text-input__action-item fff-flex-text-input__suffix-action">{{ $action }}</div>
    @endif
@endforeach
