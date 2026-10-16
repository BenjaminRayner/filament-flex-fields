/** Resolve the owning Livewire instance, including teleported modal panels. */
export function createSelectTransportMixin() {
    return {
        resolveLivewire() {
            // SSR supplies the actual component id; teleport $el/$wire can point
            // at another evaluating element, so identity wins over proximity.
            if (this.livewireId && typeof Livewire !== 'undefined' && typeof Livewire.find === 'function') {
                const wire = Livewire.find(this.livewireId)
                if (wire?.callSchemaComponentMethod) return wire
            }
            const host = (this._pickerHostElement ?? this.$el)?.closest('[wire\\:id], [wire-id]')
            const id = host?.getAttribute('wire:id') ?? host?.getAttribute('wire-id')
            if (id && typeof Livewire !== 'undefined' && typeof Livewire.find === 'function') {
                const wire = Livewire.find(id)
                if (wire?.callSchemaComponentMethod) return wire
            }
            return this.$wire ?? null
        },

        async callSchemaMethod(method, params = {}) {
            if (this._selectDisposed) throw new DOMException('Aborted', 'AbortError')
            if (!this.componentKey) throw new Error('Select schema component identity is missing.')
            const wire = this.resolveLivewire()
            if (typeof wire?.callSchemaComponentMethod === 'function') {
                return await wire.callSchemaComponentMethod(this.componentKey, method, params)
            }
            if (typeof Livewire !== 'undefined' && typeof Livewire.fireAction === 'function' && wire?.__instance) {
                return await Livewire.fireAction(wire.__instance, 'callSchemaComponentMethod',
                    [this.componentKey, method, params], { async: true })
            }
            throw new Error('Select Livewire transport is unavailable.')
        },
    }
}
