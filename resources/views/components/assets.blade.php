@props(['component', 'capabilities' => [], 'consumer' => null, 'critical' => false, 'schemaComponent' => null, 'externalStylesheets' => [], 'planDictionary' => null])

@if ($critical)
    @php(\Bjanczak\FilamentFlexFields\Support\FlexFieldStylesheetQueue::requireCritical($component, $capabilities))
@endif

{{-- Public composition boundary: one independent consumer per rendered instance. --}}
@include('filament-flex-fields::partials.load-stylesheet', [
    'component' => $component,
    'capabilities' => $capabilities,
    'livewireKey' => $consumer,
    'assetSchemaComponent' => $schemaComponent,
    'externalStylesheets' => $externalStylesheets,
    'planDictionary' => $planDictionary,
])
