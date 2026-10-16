@props(['component'])
{{-- Reuse actual Filament child renderers: state, validation, actions and asset owners survive. --}}
{{ $component->getChildSchema() }}
