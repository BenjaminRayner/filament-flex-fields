import { flattenHeadlessOptions, headlessOptionValue } from '../../../components/select-field/headless-select-options.js'
import { pruneSelectedValuesToAllowed } from '../../../components/select-field/headless-select-state.js'

/** Publish valid option data atomically; prune only after authoritative full-list success. */
export function createSelectOptionPublicationMixin() {
    return {
        applyRemoteOptions(nextOptions, { pruneSelection = true } = {}) {
            if (this._selectDisposed) return
            const normalized = Array.isArray(nextOptions) ? nextOptions : []
            // Validate the whole tree before publishing any observable state.
            const flat = flattenHeadlessOptions(normalized)

            this._optionProjectionRevision = (this._optionProjectionRevision ?? 0) + 1
            this.options = normalized
            this.flatOptions = flat
            this._engineOptionsFingerprint = this.fingerprintHeadlessOptions?.(flat) ?? null
            this.invalidateVirtualRowProjection()
            this.virtualScrollTick = (this.virtualScrollTick ?? 0) + 1
            this._engine?.setOptions(flat)

            if (pruneSelection && this.hasDynamicOptions && !this.hasDynamicSearchResults) {
                const allowed = flat.map((option) => String(headlessOptionValue(option)))
                const nextSelected = pruneSelectedValuesToAllowed(this.comboboxSelectedValues, allowed)

                if (nextSelected.length !== this.comboboxSelectedValues.length
                    || nextSelected.some((value, index) => String(value) !== String(this.comboboxSelectedValues[index] ?? ''))) {
                    this._programmaticSelectionWrite = true

                    try {
                        this._engine?.setSelectedValues(nextSelected)
                    } finally {
                        this._programmaticSelectionWrite = false
                    }
                }
            }

            this._syncFromEngine()
            this.markHeadlessDisplayReady?.()

            this.queueSelectLayoutRefresh({ checks: true })
        },

    }
}
