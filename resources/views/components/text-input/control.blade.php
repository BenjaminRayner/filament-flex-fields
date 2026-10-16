{{-- Presentation primitive. The caller owns binding, assets, wrapper and state. --}}
@props(['interactive' => false, 'value' => null])
<input @if ($value !== null) value="{{ $value }}" @endif {{ $attributes->class(['fff-flex-text-input__input', 'fi-input']) }} @if ($interactive) x-ref="input" @endif />
