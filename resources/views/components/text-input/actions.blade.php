@foreach ($options['toolsOrder'] as $tool)
    @if ($tool === 'emoji' && $options['emojiPicker'])
        @include('filament-flex-fields::forms.components.partials.flex-text-input-emoji-picker')
    @elseif ($tool === 'dictation' && $options['speechDictation'])
        @include('filament-flex-fields::components.text-input.tools.dictation')
    @elseif ($tool === 'clear' && $options['clearable'])
        @include('filament-flex-fields::components.text-input.tools.clear')
    @elseif ($tool === 'icon' && $options['iconPicker'])
        @foreach ($field->getChildComponents('iconPicker') as $iconPicker)
            {{ $iconPicker }}
        @endforeach
    @elseif ($tool === 'actions')
        @include('filament-flex-fields::components.text-input.tools.actions')
    @endif
@endforeach
