import { loadTrackMarkerImages } from './marker-images.js'
import { applyDeliveryAppearance } from './delivery-appearance.js'
import { loadMapRuntime } from './assets.js'
import { runtime, resetMapSurface } from './resources.js'
import { hasCoordinates } from '../../support/mapbox-geocoding.js'
import { createResourceScope } from '../../runtime/lifecycle/resource-scope.js'

function mapOptions(field) {
    const config = field.mapConfiguration
    const center = config.selectionMode === 'point' && hasCoordinates(field.state)
        ? [Number(field.state.lng), Number(field.state.lat)]
        : [field.defaultCenter[1], field.defaultCenter[0]]
    const bounds = config.bounds
    return {
        container: field.$refs.mapCanvas,
        ...(config.provider === 'mapbox' ? { accessToken: field.accessToken } : {}),
        style: config.style ?? config.styles[field.currentMapTheme()],
        center,
        zoom: Math.min(config.maxZoom, Math.max(config.minZoom, field.defaultZoom)),
        minZoom: config.minZoom,
        maxZoom: config.maxZoom,
        pitch: config.pitch,
        bearing: config.bearing,
        scrollZoom: config.scrollZoom,
        cooperativeGestures: config.cooperativeGestures,
        attributionControl: false,
        ...(bounds ? { maxBounds: [[bounds[0], bounds[1]], [bounds[2] < bounds[0] ? bounds[2] + 360 : bounds[2], bounds[3]]] } : {}),
    }
}

/** Construction, SDK events and readiness are one cancellable map generation. */
export function createMapBoot() {
    return {
        async bootstrapMap() {
            const resources = runtime(this)
            const config = this.mapConfiguration
            if (this.__fffPickerDisposed || resources.map || resources.booting) return
            if (config.provider === 'mapbox' && !this.accessToken) {
                this.mapError = this.labels.missingToken
                this.mapLoading = false
                return
            }
            const generation = ++resources.epoch
            const current = () => !this.__fffPickerDisposed && resources.epoch === generation
            resources.booting = true
            this.mapLoading = true
            this.mapError = null
            const scope = createResourceScope()
            resources.surfaceScope = scope
            try {
                const [sdk] = await Promise.all([loadMapRuntime(config.provider, config.assets), loadTrackMarkerImages(config, this._pickerHostElement.ownerDocument, scope)])
                if (!current()) return
                resources.sdk = sdk
                if (config.workerUrl && typeof sdk.setWorkerUrl === 'function') sdk.setWorkerUrl(config.workerUrl)
                else if (config.workerUrl && config.provider === 'mapbox') sdk.workerUrl = config.workerUrl
                resources.theme = this.currentMapTheme()
                const map = new sdk.Map(mapOptions(this))
                resources.map = map
                const listen = (event, handler) => {
                    const guarded = (...args) => { if (current() && resources.map === map) handler(...args) }
                    map.on(event, guarded)
                    scope.defer(() => map.off?.(event, guarded))
                }
                map.addControl(new sdk.AttributionControl({ compact: false }), 'bottom-right')
                if (config.scale) map.addControl(new sdk.ScaleControl({ unit: config.scaleUnit }), 'bottom-left')
                listen('style.load', () => { applyDeliveryAppearance(map, config.deliveryAppearance); this.installMapLayers(); this.refreshEditingHandles() })
                const deadline = setTimeout(() => {
                    if (!current() || this.mapReady) return
                    this.mapError = this.labels.loadFailed
                    this.mapLoading = false
                }, 20000)
                scope.defer(() => clearTimeout(deadline))
                listen('load', () => {
                    clearTimeout(deadline)
                    resources.startupFailure = null
                    this.mapReady = true
                    this.mapLoading = false
                    this.mapError = null
                    map.resize()
                    this.installMapLayers()
                    this.syncSelectedMarker()
                    if (config.fitFeatures) this.fitMapToFeatures()
                    if (config.selectionMode === 'point' && !hasCoordinates(this.state)) void this.resolveInitialLocation()
                })
                listen('click', event => { void this.onMapClick(event) })
                listen('sourcedata', event => {
                    if (event.sourceId === 'fff-state' && event.isSourceLoaded) resources.clusterSelectionPending = false
                })
                listen('render', () => this.syncClusteredMarkerVisibility())
                listen('mousemove', event => this.onMapHover(event))
                listen('movestart', () => this.closeFeatureTooltip())
                scope.listen(map.getCanvas(), 'pointerleave', () => {
                    this.closeFeatureTooltip()
                    map.getCanvas().style.cursor = ''
                })
                // Individual tiles/glyphs can fail while the map still becomes ready.
                // Only the bounded startup deadline or constructor/resource failure exposes Retry.
                listen('error', event => { if (!this.mapReady) resources.startupFailure = event.error ?? null })
            } catch {
                if (!current()) return
                resetMapSurface(this)
                this.mapError = this.labels.loadFailed
                this.mapLoading = false
            } finally {
                if (resources.epoch === generation) resources.booting = false
            }
        },
        retryMap() {
            if (this.__fffPickerDisposed) return
            this.invalidateLocationIntent()
            this.cancelDrawing()
            resetMapSurface(this)
            this.mapReady = false
            void this.bootstrapMap()
        },
    }
}
