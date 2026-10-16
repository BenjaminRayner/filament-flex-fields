import { runtime, disposeMap } from './resources.js'
import { createMapBoot } from './boot.js'
import { bindMapEnvironment } from './environment.js'
import { createRequestCoordinator } from '../../select/async/request-coordinator.js'

/** Stable field owner. SDK objects and effects never enter Alpine's reactive state. */
export function createLocationLifecycle() {
    return {
        ...createMapBoot(),
        closeLocationSearch() {
            this._pickerHostElement?.dispatchEvent(new CustomEvent('fff-parent-overlay-close', { bubbles: true }))
        },
        init() {
            this._pickerHostElement ??= this.$el
            if (!this.beginPickerInitialization()) return
            const resources = runtime(this)
            this.initializeMapExperiences?.()
            resources.requests = createRequestCoordinator()
            this.ensurePickerResourceScope().defer(() => resources.requests.dispose())
            this.ensurePickerResourceScope().listen(this._pickerHostElement.ownerDocument.defaultView, 'fff-map-feature-form-saved', event => this.applyFeatureForm(event))
            this.syncLocationSummary()
            this.$watch('state', () => {
                if (this.__fffPickerDisposed) return
                const ownUpdate = JSON.stringify(this.state) === resources.ownState
                if (!ownUpdate) {
                    this.closeFeaturePopup()
                    this.invalidateLocationIntent()
                    this.cancelDrawing()
                    resources.history = []
                    resources.future = []
                    resources.historyBytes = 0
                    this.canUndo = false
                    this.canRedo = false
                    this.selectedFeatureId = null
                }
                resources.ownState = null
                this.syncLocationSummary()
                if (!ownUpdate) {
                    resources.editHandoff?.dispose(); resources.editHandoff = null
                    this.refreshMapLayers()
                    this.refreshEditingHandles()
                }
            })
            this.$nextTick(() => { if (!this.__fffPickerDisposed) bindMapEnvironment(this) })
        },
        destroy() {
            if (this.__fffPickerDisposed) return
            this.closeLocationSearch()
            this.disposePickerResources()
            disposeMap(this)
        },
        currentMapTheme() {
            const theme = this.mapConfiguration.theme
            if (theme !== 'auto') return theme
            const root = (this._pickerHostElement?.ownerDocument ?? document).documentElement
            return root.classList.contains('dark') || root.dataset.theme === 'dark' ? 'dark' : 'light'
        },
        syncMapTheme() {
            const resources = runtime(this)
            const theme = this.currentMapTheme()
            if (this.__fffPickerDisposed || !resources.map || resources.theme === theme || this.mapConfiguration.style) return
            resources.theme = theme
            resources.map.setStyle(this.mapConfiguration.styles[theme])
        },
    }
}
