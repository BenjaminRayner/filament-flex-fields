import { metadataBytes, validMetadata, validMetadataText } from './metadata.js'

const TEXT_KEYS = ['label', 'description', 'detail', 'value', 'metric', 'tooltip', 'color']

/** Matches FeatureProperties PHP: declared JSON data, bounded UTF-8 and no geometry mutation. */
export function validFeatureProperties(properties, extraKeys = []) {
    if (!properties || typeof properties !== 'object' || Array.isArray(properties)) return false
    for (const key of TEXT_KEYS) {
        const value = properties[key]
        if (value != null && (!validMetadataText(value) || key === 'color' && !/^#[\da-f]{6}$/i.test(value))) return false
    }
    if ('clickable' in properties && typeof properties.clickable !== 'boolean') return false
    for (const key of extraKeys) {
        const value = properties[key]
        if (value === undefined) continue
        if (!validMetadata(value)) return false
    }
    const owned = Object.fromEntries([...TEXT_KEYS, 'clickable', ...extraKeys].filter(key => properties[key] !== undefined).map(key => [key, properties[key]]))
    return metadataBytes(owned) <= 65536
}

function ordered(value) {
    if (Array.isArray(value)) return value.map(ordered)
    if (value && typeof value === 'object') return Object.fromEntries(Object.keys(value).sort().map(key => [key, ordered(value[key])]))
    return value
}
export const sameFeature = (a, b) => JSON.stringify(ordered(a)) === JSON.stringify(ordered(b))

export function createFeatureProperties() {
    return {
        openFeatureAction(id, name = 'editMapFeature') {
            if (this.readOnly || this.__fffPickerDisposed) return
            const feature = this.geometryFeatures().find(item => String(item.id) === String(id))
            if (!feature || feature.geometry.type !== 'Point' && !this.mapConfiguration.editableZones) return
            void this.$wire?.mountAction?.(name, { id: String(id), before: JSON.parse(JSON.stringify(feature)) }, { schemaComponent: this.componentKey })
        },
        applyFeatureForm(event) {
            const { owner, id, before, properties } = event.detail ?? {}
            if (owner !== this.componentKey || this.readOnly || this.__fffPickerDisposed) return
            const current = this.geometryFeatures().find(item => String(item.id) === String(id))
            if (!current || !sameFeature(current, before)) { this.selectionError = this.labels.featureChanged; return }
            this.updateFeatureProperties(id, properties)
        },
        updateFeatureProperty(id, key, value) { return this.updateFeatureProperties(id, { [key]: value }) },
        updateFeatureProperties(id, patch) {
            if (this.readOnly || this.__fffPickerDisposed) return false
            const feature = this.geometryFeatures().find(item => String(item.id) === String(id))
            if (!feature || feature.geometry.type !== 'Point' && !this.mapConfiguration.editableZones) return false
            const allowed = new Set([...TEXT_KEYS, 'clickable', ...(this.mapConfiguration.extraFeatureProperties ?? [])])
            if (!patch || typeof patch !== 'object' || Array.isArray(patch) || Object.keys(patch).some(key => !allowed.has(key))
                || !validFeatureProperties(patch, this.mapConfiguration.extraFeatureProperties)) { this.selectionError = this.labels.invalidProperties; return false }
            const properties = { ...feature.properties, ...patch }
            return this.commitCollection({ type: 'FeatureCollection', features: this.geometryFeatures().map(item => String(item.id) === String(id) ? { ...item, properties } : item) }, { preservePopup: true })
        },
    }
}
