<x-filament-panels::page>
    {{ $this->form }}
    @if ($this->dynamicDemo)
        {{ $this->firstFlexAction }}
        <a href="/cost?case=one" wire:navigate>Open field page</a>
    @elseif ($this->scenario === 'one')
        <a href="/cost?case=dynamic" wire:navigate>Return to empty page</a>
    @endif
</x-filament-panels::page>
