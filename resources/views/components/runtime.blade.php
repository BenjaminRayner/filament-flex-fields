@props(['phase'])

@php
    $position = \Bjanczak\FilamentFlexFields\Support\Assets\Rendering\RuntimeRenderPhase::tryFrom($phase)
        ?? throw new \InvalidArgumentException('Unknown FLEX runtime layout phase.');
@endphp

{!! \Bjanczak\FilamentFlexFields\Support\Bootstrap\RenderHookRegistration::renderStandalone($position) !!}
