@php
    use Filament\Support\Enums\IconSize;
    use Filament\Forms\Components\TextInput\Actions\CopyAction;
    use Filament\Forms\Components\TextInput\Actions\ShowPasswordAction;
    use Filament\Forms\Components\TextInput\Actions\HidePasswordAction;
@endphp
<div class="fff-flex-text-input__action-item fff-flex-text-input__clear" x-show="canClear"
        @if ($presentation->value === '') style="display:none" @endif>
        <button type="button" class="fff-flex-text-input__action-btn fff-flex-text-input__action-btn--clear"
            aria-label="{{ $options['clearLabel'] }}" x-on:click="clearInput()">
            {{ \Filament\Support\generate_icon_html($getClearIcon(), size: IconSize::Small, attributes: new \Illuminate\View\ComponentAttributeBag(['class' => 'fff-flex-text-input__action-icon'])) }}
        </button>
    </div>
