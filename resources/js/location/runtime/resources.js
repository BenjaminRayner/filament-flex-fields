const instances = new WeakMap()

/** DOM identity survives Alpine's different merged-scope proxies. */
export function runtime(field) {
    const owner = field._pickerHostElement ?? field.$el ?? field.$root ?? field
    let resources = instances.get(owner)
    if (!resources) {
        resources = {
            map: null, sdk: null, marker: null, popup: null, tooltip: null,
            surfaceScope: null, handles: [], handleBindings: [], observers: [], disposers: [],
            frame: null, editFrame: null, draftFrame: null, editing: null, editGuide: null,
            startupFailure: null, booting: false, epoch: 0, gpsEpoch: 0,
            history: [], future: [], historyBytes: 0, ownState: null,
            pointFilterId: null, zoneFilterId: null,
        }
        instances.set(owner, resources)
    }
    return resources
}

function safely(effect) { try { effect() } catch { /* Disposal continues for every independent resource. */ } }

/** Reset the SDK surface without removing field observers, requests or undo state. */
export function resetMapSurface(field) {
    const resources = runtime(field)
    resources.epoch++
    resources.booting = false
    resources.startupFailure = null
    resources.surfaceScope?.dispose()
    resources.surfaceScope = null
    safely(() => resources.experience?.dispose())
    resources.experience = null
    safely(() => resources.editHandoff?.dispose())
    resources.editHandoff = null
    safely(() => resources.editPreview?.dispose())
    resources.editPreview = null
    for (const key of ['frame', 'editFrame', 'draftFrame']) {
        if (resources[key] !== null) cancelAnimationFrame(resources[key])
        resources[key] = null
    }
    clearTimeout(resources.readyTimeout)
    resources.disposers.splice(0).forEach(effect => safely(effect))
    for (const element of [...resources.handles, resources.marker, resources.popup, resources.tooltip, resources.map]) {
        if (element) safely(() => element.remove())
    }
    resources.map = resources.marker = resources.popup = resources.tooltip = null
    resources.handles = []
    resources.handleBindings = []
    resources.editing = resources.editGuide = resources.previewPoint = null
    resources.markerDragging = false
    resources.clusterSelectionPending = false
    resources.pointFilterId = resources.zoneFilterId = null
    resources.popupCard = resources.popupFeatureId = resources.popupPreviousFocus = resources.tooltipIdentity = null
}

export function disposeMap(field) {
    const resources = runtime(field)
    resources.gpsEpoch++
    resources.requests?.dispose()
    resetMapSurface(field)
    resources.observers.splice(0).forEach(observer => safely(() => observer.disconnect()))
    resources.history = []
    resources.future = []
    resources.historyBytes = 0
    resources.ownState = null
}
