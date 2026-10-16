import { runtime } from '../../runtime/resources.js'
import { createGeometryPreview, handoffGeometryPreview } from './preview.js'

export function cancelEdit(field) {
    const resources = runtime(field)
    if (resources.editFrame !== null) cancelAnimationFrame(resources.editFrame)
    resources.editFrame = null
    resources.editing = null
    resources.editGuide = null
    resources.editPreview?.dispose()
    resources.editPreview = null
}

/** Immutable baseline, one preview per paint, one validated commit per gesture. */
export function editSession(field, feature) {
    const resources = runtime(field)
    const epoch = resources.epoch
    const before = JSON.stringify(field.state)
    const baseline = JSON.parse(before).features
    const collection = next => ({
        type: 'FeatureCollection',
        features: baseline.map(item => String(item.id) === String(feature.id) ? next : item),
    })
    const current = () => !field.__fffPickerDisposed && resources.epoch === epoch
        && JSON.stringify(field.state) === before && String(field.selectedFeatureId) === String(feature.id)
    let next = feature
    let guide = null

    return {
        begin() {
            cancelEdit(field)
            resources.editHandoff?.dispose()
            resources.editHandoff = null
            field.closeFeaturePopup?.()
            field.closeFeatureTooltip?.()
            resources.editing = feature.id
            resources.editPreview = createGeometryPreview(field, resources.map, feature)
            resources.editPreview.paint(feature, null)
        },
        preview(value, nextGuide) {
            next = value
            guide = nextGuide
            if (resources.editFrame !== null) return
            resources.editFrame = requestAnimationFrame(() => {
                resources.editFrame = null
                if (field.__fffPickerDisposed || resources.epoch !== epoch || resources.editing !== feature.id) return
                // Lazy transforms also coalesce geometry/snap work, not just the canvas paint.
                const value = typeof next === 'function' ? next() : next
                const nextGuide = typeof guide === 'function' ? guide(value) : guide
                resources.editPreview?.paint(value, nextGuide)
                for (const handle of resources.handleBindings ?? []) {
                    if (!handle.button.classList.contains('is-dragging')) {
                        handle.marker.setLngLat(handle.locate(value))
                    }
                }
            })
        },
        finish(value) {
            if (!current()) {
                if (resources.epoch === epoch && resources.editing === feature.id) this.cancel()
                return
            }
            const preview = resources.editPreview
            resources.editPreview = null
            if (preview && resources.map?.on) {
                preview.paint(value, null)
                resources.editHandoff = handoffGeometryPreview(resources.map, preview)
            } else preview?.dispose()
            cancelEdit(field)
            // Full topology, overlap, bounds validation and history happen only on release.
            if (!field.commitCollection(collection(value)) || JSON.stringify(value) === JSON.stringify(feature)) {
                resources.editHandoff?.dispose()
                resources.editHandoff = null
                field.refreshMapLayers()
                field.refreshEditingHandles()
            }
        },
        cancel() {
            cancelEdit(field)
            if (!field.__fffPickerDisposed && resources.epoch === epoch) {
                field.refreshMapLayers()
                field.refreshEditingHandles()
            }
        },
    }
}
