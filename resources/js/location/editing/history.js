import { runtime } from '../runtime/resources.js'
import { collectionError, emptyCollection } from '../geometry/collection.js'

const HISTORY_BYTES = 2097152
function boundHistory(r, limit) {
    let bytes = [...r.history, ...r.future].reduce((sum, entry) => sum + entry.length * 2, 0)
    while (r.history.length + r.future.length > limit || bytes > HISTORY_BYTES) {
        const dropped = r.history.length ? r.history.shift() : r.future.shift()
        bytes -= dropped.length * 2
    }
    r.historyBytes = r.history.reduce((sum, entry) => sum + entry.length * 2, 0)
}

/** Atomic history uses bounded serialized values; SDK and draft objects never enter Alpine state. */
export function createGeometryHistory() {
    return {
        canUndo: false, canRedo: false, selectedFeatureId: null,
        geometryFeatures() { return this.state?.type === 'FeatureCollection' ? this.state.features : [] },
        commitCollection(collection, { history = true, preservePopup = false } = {}) {
            if (this.readOnly || this.__fffPickerDisposed) return false
            const error = collectionError(collection, this.mapConfiguration, this.labels)
            if (error) { this.selectionError = error; return false }
            const serialized = JSON.stringify(collection), before = JSON.stringify(this.state ?? emptyCollection())
            if (serialized === before) return true
            const r = runtime(this)
            if (history) {
                r.history.push(before); r.historyBytes += before.length * 2; r.future = []
                boundHistory(r, this.mapConfiguration.historyLimit)
            }
            if (!preservePopup) this.closeFeaturePopup?.()
            r.ownState = serialized; this.state = JSON.parse(serialized); this.selectionError = null
            if (this.selectedFeatureId != null && !this.geometryFeatures().some(feature => String(feature.id) === String(this.selectedFeatureId))) this.selectedFeatureId = null
            this.canUndo = r.history.length > 0; this.canRedo = r.future.length > 0
            this.refreshMapLayers(); this.refreshEditingHandles()
            if (preservePopup) this.refreshFeaturePopup?.()
            const previous = new Map(JSON.parse(before).features.map(feature => [String(feature.id), feature]))
            const current = new Map(this.geometryFeatures().map(feature => [String(feature.id), feature]))
            for (const [id, feature] of current) {
                const old = previous.get(id)
                if (!old || JSON.stringify(feature) !== JSON.stringify(old)) this._pickerHostElement?.dispatchEvent(new CustomEvent(`fff-map-feature-${old ? 'updated' : 'added'}`, { bubbles: true, detail: { id, feature, previousFeature: old ?? null, statePath: this.statePath, origin: history ? 'edit' : 'history' } }))
            }
            for (const [id, feature] of previous) if (!current.has(id)) this._pickerHostElement?.dispatchEvent(new CustomEvent('fff-map-feature-deleted', { bubbles: true, detail: { id, feature, statePath: this.statePath, origin: history ? 'edit' : 'history' } }))
            return true
        },
        undoGeometry() {
            if (this.readOnly) return
            this.cancelDrawing()
            const r = runtime(this), previous = r.history.at(-1)
            if (!previous) return
            const current = JSON.stringify(this.state)
            if (!this.commitCollection(JSON.parse(previous), { history: false })) return
            r.history.pop(); r.historyBytes -= previous.length * 2; r.future.push(current)
            boundHistory(r, this.mapConfiguration.historyLimit)
            this.canUndo = r.history.length > 0; this.canRedo = r.future.length > 0
        },
        redoGeometry() {
            if (this.readOnly) return
            this.cancelDrawing()
            const r = runtime(this), next = r.future.at(-1)
            if (!next) return
            const current = JSON.stringify(this.state)
            if (!this.commitCollection(JSON.parse(next), { history: false })) return
            r.future.pop(); r.history.push(current); r.historyBytes += current.length * 2
            boundHistory(r, this.mapConfiguration.historyLimit)
            this.canUndo = r.history.length > 0; this.canRedo = r.future.length > 0
        },
        deleteFeature(id) {
            if (this.readOnly || !this.mapConfiguration.deletableFeatures) return
            const features = this.geometryFeatures().filter(feature => String(feature.id) !== String(id))
            this.commitCollection({ type: 'FeatureCollection', features })
        },
    }
}
