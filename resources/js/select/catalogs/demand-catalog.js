/** Coalesce consumers and invalidate only the field revision, never another field's catalog. */
export function createDemandCatalogMixin({itemsKey, loadedKey, loadingKey, poolKey, read, seed = () => []}) {
    return {
        _demandCatalogRevision: 0, _demandCatalogFlight: null, _demandCatalogBound: false, catalogError: null,
        bindDemandCatalog() {
            if (this._demandCatalogBound) return
            this._demandCatalogBound = true
            const scope = this.ensurePickerResourceScope()
            scope.defer(() => { this._demandCatalogRevision++; this._demandCatalogFlight = null })
            scope.listen(document, 'livewire:navigated', () => {
                if (!this[poolKey]) return
                this._demandCatalogRevision++; this._demandCatalogFlight = null
                this[itemsKey] = seed(this); this[loadedKey] = false; this[loadingKey] = false
                this.cancelPendingPickerOpen?.()
            })
        },
        ensureDemandCatalog() {
            if (this.__fffPickerDisposed || !this[poolKey] || this[loadedKey]) return Promise.resolve(this[itemsKey])
            this.bindDemandCatalog()
            if (this._demandCatalogFlight) return this._demandCatalogFlight
            const revision = this._demandCatalogRevision
            this[loadingKey] = true; this.catalogError = null
            const flight = Promise.resolve().then(() => read(this)).then(items => {
                if (!this.__fffPickerDisposed && revision === this._demandCatalogRevision) {
                    this[itemsKey] = items; this[loadedKey] = items.length > 0
                }
                return items
            }).catch(() => {
                if (!this.__fffPickerDisposed && revision === this._demandCatalogRevision) this.catalogError = 'catalog-unavailable'
                return []
            }).finally(() => {
                if (revision === this._demandCatalogRevision) { this._demandCatalogFlight = null; this[loadingKey] = false }
            })
            this._demandCatalogFlight = flight
            return flight
        },
    }
}
