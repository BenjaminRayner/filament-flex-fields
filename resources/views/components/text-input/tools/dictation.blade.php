@php
    use Filament\Support\Enums\IconSize;
    use Filament\Forms\Components\TextInput\Actions\CopyAction;
    use Filament\Forms\Components\TextInput\Actions\ShowPasswordAction;
    use Filament\Forms\Components\TextInput\Actions\HidePasswordAction;
@endphp
<div class="fff-flex-text-input__action-item fff-flex-text-input__dictation">
        <button type="button" class="fff-flex-text-input__action-btn fff-flex-text-input__action-btn--dictation"
            disabled x-bind:disabled="!speechSupported" x-bind:title="!speechSupported ? options.messages.unsupported : options.speechDictationLabel"
            x-bind:class="{ 'is-listening': isListening }" x-bind:aria-pressed="isListening"
            :aria-label="!speechSupported ? options.messages.unsupported : (isListening ? options.messages.stop : options.speechDictationLabel)"
            aria-label="{{ $getSpeechDictationLabel() }}" x-on:click="toggleDictation()">
            {{ \Filament\Support\generate_icon_html($getMicrophoneIcon(), size: IconSize::Small, attributes: new \Illuminate\View\ComponentAttributeBag(['class' => 'fff-flex-text-input__action-icon'])) }}
        </button>
    </div>
