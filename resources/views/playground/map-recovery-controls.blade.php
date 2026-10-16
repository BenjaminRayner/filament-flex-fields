<div class="fff-map-recovery-demo" x-data="{
    sequence: 0, observedAt: Date.now(), status: 'Waiting for a position fix',
    send(progress, elapsed, description) {
        const owner = $el.closest('.fi-section').querySelector('.fff-location[aria-label=\'Connection recovery\']');
        if (!owner) return;
        this.observedAt += elapsed;
        owner.dispatchEvent(new CustomEvent('fff-map-layer-update', { detail: {
            sequence: ++this.sequence, layers: {}, motion: { progress, observedAt: this.observedAt },
            card: { description }
        }}));
        this.status = description;
    }
}">
    <div class="fff-map-recovery-actions">
        <x-filament::button type="button" size="sm" color="gray" x-on:click="send(.15, 1000, 'Position received · 15% of route')">Receive position</x-filament::button>
        <x-filament::button type="button" size="sm" color="gray" x-on:click="status = 'Connection lost · holding the last known position'">Lose connection</x-filament::button>
        <x-filament::button type="button" size="sm" x-on:click="send(.85, 60000, 'Connection restored · catching up along the route')">Restore after 60 seconds</x-filament::button>
        <x-filament::button type="button" size="sm" color="gray" x-on:click="send(0, 1000, 'Demo reset · ready for another simulation')">Reset</x-filament::button>
    </div>
    <p class="fff-map-recovery-status" role="status" x-text="status">Waiting for a position fix</p>
</div>
