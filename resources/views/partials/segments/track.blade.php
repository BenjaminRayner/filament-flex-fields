{{-- The only segment track markup: fields, tabs and locale tabs all render this. --}}
<div x-ref="track" wire:ignore.self data-fff-segment-track
    @class([
        'fff-segment-track', 'fff-segment-track--'.$size,
        'fff-segment-track--ghost' => $variant === 'ghost',
        'fff-segment-track--in-shell' => ! $fullWidth,
    ])
>
    <div x-ref="indicator" wire:ignore aria-hidden="true"
        @class(['fff-segment-indicator', 'fff-segment-indicator--ghost' => $variant === 'ghost'])
    ></div>
    @foreach ($items as $item)
        @php($isSelected = $selectedKey !== null && (string) $item->key === $selectedKey)
        @if (! $loop->first && $separators)
            <span @class([
                'fff-segment-separator',
                'is-hidden' => $role === 'group'
                    ? (filled($items[$loop->index - 1]->visibilityJs) && $items[$loop->index - 1]->initiallyVisible !== true)
                        || (filled($item->visibilityJs) && $item->initiallyVisible !== true)
                    : $selectedIndex !== false && in_array($loop->index - 1, [$selectedIndex - 1, $selectedIndex], true),
            ]) data-segment-separator="{{ $loop->index - 1 }}" aria-hidden="true"></span>
        @endif
        <button type="button" @if ($role !== 'group') role="{{ $role === 'tablist' ? 'tab' : 'radio' }}" @endif
            class="fff-segment-item fff-segment-item--{{ $size }}"
            @if (filled($identity)) wire:key="{{ $identity }}.segment.{{ $item->key }}" @endif
            aria-label="{{ $item->label }}"
            data-segment-value="{{ $item->key }}"
            @if ($retainIntent ?? false) data-fff-open-trigger data-fff-intent="select" @endif
            data-segment-selected="{{ $isSelected ? 'true' : 'false' }}"
            @if ($role === 'tablist')
                aria-selected="{{ $isSelected ? 'true' : 'false' }}"
                aria-controls="{{ $item->panelId }}" id="{{ $item->panelId }}-trigger"
            @elseif ($role !== 'group')
                aria-checked="{{ $isSelected ? 'true' : 'false' }}"
            @endif
            tabindex="{{ ($role === 'group' || (string) $item->key === $focusKey) && ! $disabled && ! $item->disabled ? '0' : '-1' }}"
            @disabled($disabled || $item->disabled)
            x-on:click="select(@js($item->key))"
            @if (filled($item->visibilityJs))
                @if ($item->initiallyVisible !== true) x-cloak @endif
                @if ($item->initiallyVisible === false) style="display: none;" @endif
                x-show="{!! $item->visibilityJs !!}"
            @endif
            @if (filled($item->tooltip)) x-tooltip="{ content: @js($item->tooltip), theme: 'fff', arrow: false, allowHTML: false }" @endif
        >
            @if (filled($item->icon)) <x-filament::icon :icon="$item->icon" /> @endif
            <span @class([
                'fff-segment-item__label', 'sr-only' => $iconOnly,
                'fff-segment-item__label--selected' => $expandSelectedLabel && ! $iconOnly,
            ])>{{ $item->label }}</span>
            @if (filled($item->badge))
                <x-filament::badge :color="$item->badgeColor" size="xs" :tooltip="$item->badgeTooltip" class="fff-segment-item__badge">
                    {{ $item->badge }}
                </x-filament::badge>
            @endif
        </button>
    @endforeach
</div>
