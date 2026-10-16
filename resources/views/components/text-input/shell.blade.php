{{-- Shared presentation: the consuming field owns value, binding and assets. --}}
@props(['actionsVisible' => null, 'actionsInitiallyVisible' => true, 'prefixIcon' => null, 'actionsFramed' => true])
<div {{ $attributes->class(['fff-flex-text-input__shell']) }}>
    <div class="fff-flex-text-input__row">
        @if ($prefixIcon !== null)
            <span class="fff-flex-text-input__leading-icon" aria-hidden="true"><x-filament::icon :icon="$prefixIcon" /></span>
        @endif
        <div class="fff-flex-text-input__control">{{ $slot }}</div>
        @isset($actions)
            <div @class(['fff-flex-text-input__action-group', 'fff-flex-text-input__action-group--plain' => ! $actionsFramed])
                @if ($actionsVisible !== null) x-show="{{ $actionsVisible }}" @endif
                @if (! $actionsInitiallyVisible) style="display:none" @endif>{{ $actions }}</div>
        @endisset
    </div>
</div>
