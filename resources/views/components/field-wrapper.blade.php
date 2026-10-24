@props(['field', 'component', 'inlineLabelVerticalAlignment' => null, 'labelSrOnly' => null])
@php
    $embedded = app(\Bjanczak\FilamentFlexFields\Support\Rendering\NativeFieldWrapper::class)
        ->render($field, $component, (string) $slot, $attributes, $inlineLabelVerticalAlignment, $labelSrOnly);
@endphp
@if ($embedded !== null)
    {!! $embedded !!}
@else
    <x-dynamic-component :component="$component" :field="$field" :inline-label-vertical-alignment="$inlineLabelVerticalAlignment"
        :label-sr-only="$labelSrOnly" :attributes="$attributes">{{ $slot }}</x-dynamic-component>
@endif
