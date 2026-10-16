import {
    filterHeadlessOptionTree,
    flattenHeadlessOptions,
    headlessOptionIsDisabled,
    headlessOptionLabelHtml,
    headlessOptionValue,
    limitHeadlessOptionTree,
} from './headless-select-options.js'
import {
    canWriteHeadlessWireState,
    commitHeadlessSelectionToWire,
} from './headless-select-state.js'
import { DEFAULT_VIRTUALIZE_THRESHOLD } from '../../core/combobox-engine.js'
import { readRawOptionSnapshot } from '../../select/options/option-snapshot.js'

function defaultGetOptionLabel(option) {
    return headlessOptionLabelHtml(option, 'dropdown')
}

/**
 * Filament `optionsLimit` is a DOM render budget. Once the list is large enough
 * to virtualize, TanStack owns that budget — truncating here would freeze scroll
 * at ~50 rows (default limit) and never reach the virt threshold.
 *
 * @param {Array<unknown>} filtered
 * @param {{ isGridLayout?: boolean, virtualizeThreshold?: number }} ctx
 */
export function shouldBypassOptionsLimitForVirtualization(filtered, ctx = {}) {
    const threshold = ctx.virtualizeThreshold ?? DEFAULT_VIRTUALIZE_THRESHOLD
    const count = flattenHeadlessOptions(filtered).length

    return count >= threshold
}

export function createHeadlessComboboxEngineSyncMixin() {
    return {
        _optionProjectionRevision: 0,
        _engineQueryRevision: 0,
        _headlessOptionProjection: null,
        applyComboboxQueryToEngine() {
            const before = this._engine?.getSnapshot().query
            this._engine?.setQuery(this.comboboxQuery)
            if (before !== this._engine?.getSnapshot().query) {
                this._engineQueryRevision++
                this._displayRowsCache = null
                this._highlightedDisplayRowKey = null
                this.invalidateVirtualRowProjection?.()
                this.virtualScrollTick = (this.virtualScrollTick ?? 0) + 1
            }
            this._syncFromEngine()
        },

        syncStateFromEngine(values) {
            const commit = commitHeadlessSelectionToWire(this.state, values, this.multiple)

            if (commit.skip || ! canWriteHeadlessWireState(this.state)) {
                return
            }

            this.state = commit.nextState
        },

        _valuesEqual(left, right) {
            if (left.length !== right.length) {
                return false
            }

            for (let index = 0; index < left.length; index++) {
                if (String(left[index]) !== String(right[index])) {
                    return false
                }
            }

            return true
        },

        _syncFromEngine() {
            if (! this._engine) {
                return
            }

            const snapshot = this._engine.getSnapshot()

            if (this.hasDynamicSearchResults && (!this.usesInlineSearchTriggerInput() || this.comboboxOpen)) {
                this.comboboxQuery = snapshot.query
            }

            this.comboboxHighlightedIndex = snapshot.highlightedIndex

            const nextSelected = Array.from(snapshot.selectedValues)

            // Keep the same array reference when values are unchanged so Alpine
            // does not re-run x-html on the trigger (optionView avatars remount).
            if (! this._valuesEqual(nextSelected, this.comboboxSelectedValues)) {
                this.comboboxSelectedValues = nextSelected
            }
        },

        comboboxFilteredOptions() {
            const flat = this.getEngineOptions()
            const result = this._engine?.filteredOptions() ?? {
                options: flat,
                meta: { startIndex: 0, endIndex: flat.length, total: flat.length },
            }

            return {
                options: result.options,
                meta: { ...result.meta },
            }
        },

        resolveHeadlessOptionProjection() {
            const source = Array.isArray(this.options) ? readRawOptionSnapshot(this.options) : []
            const query = String(this.comboboxQuery ?? '').trim()
            const signature = JSON.stringify([
                query, this._optionProjectionRevision ?? 0, this.searchable, this.hasDynamicSearchResults,
                this.isGridLayout, this.virtualizeThreshold, this.optionsLimit, this.searchableOptionFields,
            ])
            const cached = this._headlessOptionProjection
            if (cached?.source === source && cached.signature === signature) return cached
            let tree = source
            if (this.searchable && !this.hasDynamicSearchResults && query) {
                tree = filterHeadlessOptionTree(source, query, (option) => defaultGetOptionLabel(option), this.searchableOptionFields)
            }
            if (!shouldBypassOptionsLimitForVirtualization(tree, {
                isGridLayout: this.isGridLayout, virtualizeThreshold: this.virtualizeThreshold,
            })) tree = limitHeadlessOptionTree(tree, this.optionsLimit)
            const flat = flattenHeadlessOptions(tree)
            const positions = new Map()
            flat.forEach((option, index) => positions.set(String(headlessOptionValue(option)), index))
            this._headlessOptionProjection = Object.freeze({ source, signature, tree, flat, positions })
            return this._headlessOptionProjection
        },

        comboboxFilteredOptionTree() { return this.resolveHeadlessOptionProjection().tree },
        getEngineOptions() { return this.resolveHeadlessOptionProjection().flat },

        syncEngineOptions() {
            const next = flattenHeadlessOptions(readRawOptionSnapshot(this.options))
            const fingerprint = this.fingerprintHeadlessOptions(next)

            // Replacing flatOptions on every open re-runs Alpine x-html for the
            // trigger + rows (optionView avatars remount and flash ~1s).
            if (fingerprint === this._engineOptionsFingerprint) {
                return
            }

            this._optionProjectionRevision = (this._optionProjectionRevision ?? 0) + 1
            this._engineOptionsFingerprint = fingerprint
            this.flatOptions = next
            this._engine?.setOptions(next)
            this._syncFromEngine()
        },

        fingerprintHeadlessOptions(options) {
            if (! Array.isArray(options) || options.length === 0) {
                return '0'
            }

            // The wire transport is JSON data; include disabled, descriptions and rich metadata.
            const fingerprint = JSON.stringify(readRawOptionSnapshot(options))

            return fingerprint
        },

        isHeadlessOptionDisabled(option) {
            return headlessOptionIsDisabled(option)
        },

        headlessOptionFlatIndex(value) {
            const normalized = String(value)

            return this.resolveHeadlessOptionProjection().positions.get(normalized) ?? -1
        }
    }
}
