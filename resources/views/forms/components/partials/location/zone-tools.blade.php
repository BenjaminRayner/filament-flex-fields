<div class="fff-location__tools fff-map-controls" role="toolbar" aria-label="{{ __('filament-flex-fields::location.'.($zones ? 'zone' : 'marker')) }}" x-show="mapReady" x-cloak>
    <div class="fff-map-controls__capsule" role="group">
        @foreach ($mapConfiguration['drawingTools'] as $tool)
            <button type="button" class="fff-map-controls__button" x-on:click="startDrawing(@js($tool))" x-bind:class="{ 'is-active': drawingTool === @js($tool) }" x-bind:disabled="!mapReady" x-bind:aria-pressed="drawingTool === @js($tool)" title="{{ __('filament-flex-fields::location.'.$tool) }}" aria-label="{{ __('filament-flex-fields::location.'.$tool) }}">
                <x-filament-flex-fields::map-icon :name="$tool === 'circle' ? 'draw-circle' : $tool" />
            </button>
        @endforeach
        @foreach (['undo' => 'undo', 'redo' => 'redo'] as $action => $icon)
            <button type="button" class="fff-map-controls__button" x-on:click="{{ $action }}Geometry()" x-bind:disabled="!{{ $action === 'undo' ? 'canUndo' : 'canRedo' }}" title="{{ __('filament-flex-fields::location.'.$action) }}" aria-label="{{ __('filament-flex-fields::location.'.$action) }}"><x-filament-flex-fields::map-icon :name="$icon" /></button>
        @endforeach
        <button type="button" class="fff-map-controls__button fff-map-controls__button--text" x-on:click="finishDrawing()" x-show="['polygon', 'polyline'].includes(drawingTool)" x-bind:hidden="!['polygon', 'polyline'].includes(drawingTool)" hidden x-bind:disabled="draftPoints.length < (drawingTool === 'polyline' ? 2 : 3)" title="{{ __('filament-flex-fields::location.finish') }}" aria-label="{{ __('filament-flex-fields::location.finish') }}">{{ __('filament-flex-fields::location.finish') }}</button>
        <button type="button" class="fff-map-controls__button fff-map-controls__button--text" x-on:click="cancelDrawing()" x-show="drawingTool" x-bind:hidden="!drawingTool" hidden x-cloak title="{{ __('filament-flex-fields::location.cancel') }}" aria-label="{{ __('filament-flex-fields::location.cancel') }}">{{ __('filament-flex-fields::location.cancel') }}</button>
    </div>
</div>
<p class="fff-location__hint fff-location__drawing-hint" x-show="drawingTool" x-cloak x-text="drawingTool === 'point' ? @js(__('filament-flex-fields::location.point_hint')) : ['polygon', 'polyline'].includes(drawingTool) ? @js(__('filament-flex-fields::location.drawing_hint')) : @js(__('filament-flex-fields::location.two_point_hint'))"></p>
