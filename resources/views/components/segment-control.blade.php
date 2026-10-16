@props([
    'items' => [], 'selected' => null, 'size' => 'md', 'variant' => 'default',
    'color' => null, 'separators' => true, 'fullWidth' => false,
    'iconOnly' => false, 'expandSelectedLabel' => false, 'disabled' => false,
    'retainIntent' => false, 'role' => 'radiogroup', 'label' => null, 'identity' => null, 'appearance' => 'default',
])

@php
    $selectedKey = $selected === null ? null : (string) $selected;
    $keys = array_map(static fn ($item): string => (string) $item->key, $items);
    $selectedIndex = array_search($selectedKey, $keys, true);
    $focusKey = $selectedKey;
    if (! in_array($focusKey, $keys, true)) {
        $focusKey = null;
        foreach ($items as $item) {
            if (! $item->disabled) { $focusKey = (string) $item->key; break; }
        }
    }
@endphp

<div {{ $attributes->class([
    'fff-segment-control', 'w-full' => $fullWidth, 'is-disabled' => $disabled,
    'fi-color-'.$color => filled($color),
    'fff-segment-control--commands' => $appearance === 'commands',
    'fff-segment-control--icon-only' => $iconOnly,
]) }} role="{{ $role }}" @if (filled($label)) aria-label="{{ $label }}" @endif
    x-on:keydown="onSegmentKeydown($event)"
>
    @if ($fullWidth)
        @include('filament-flex-fields::partials.segments.track')
    @else
        <x-filament-flex-fields::segment-overflow-shell :variant="$variant">
            @include('filament-flex-fields::partials.segments.track')
        </x-filament-flex-fields::segment-overflow-shell>
    @endif
</div>
