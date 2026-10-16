import { createResourceScope } from '../../runtime/lifecycle/resource-scope.js'

/** Position every dirty surface once per frame, including nonexclusive peers. */
export function createOverlayPositionSession({ window, overlays, requestFrame, cancelFrame, positionPanel, resolveScrollableParents, isDestroyed, onError = () => {} }) {
    const scopes = new Map()
    const dirty = new Map()
    let frame = null
    let disposed = false
    const schedulePositionUpdate = id => {
        const entry = overlays.get(id)
        if (disposed || isDestroyed() || !entry) return
        dirty.set(id, entry)
        if (frame !== null) return
        frame = requestFrame(() => {
            frame = null
            const entries = [...dirty.values()]
            dirty.clear()
            if (disposed || isDestroyed()) return
            for (const candidate of entries) {
                if (overlays.get(candidate.id) !== candidate || candidate.mode !== 'panel') continue
                try { positionPanel(candidate) }
                catch (error) {
                    // One host's geometry/renderer cannot abandon the other dirty surfaces.
                    try { onError(candidate, error) } catch { /* Reporter isolation. */ }
                }
            }
        })
    }
    const unbindPositionListeners = entry => {
        scopes.get(entry)?.dispose()
        scopes.delete(entry)
        if (dirty.get(entry.id) === entry) dirty.delete(entry.id)
        entry.scrollParents = []
        entry.scrollHandler = null
        entry.resizeHandler = null
    }
    const bindPositionListeners = entry => {
        unbindPositionListeners(entry)
        if (disposed || entry.mode !== 'panel') return
        const scope = createResourceScope()
        scopes.set(entry, scope)
        try {
            const scroll = event => {
                const NodeType = window.Node ?? globalThis.Node
                if (NodeType && event?.target instanceof NodeType && entry.panel?.contains(event.target)) return
                schedulePositionUpdate(entry.id)
            }
            const resize = () => schedulePositionUpdate(entry.id)
            entry.scrollHandler = scroll
            entry.resizeHandler = resize
            entry.scrollParents = resolveScrollableParents(entry.anchor)
            for (const parent of entry.scrollParents) scope.listen(parent, 'scroll', scroll, { passive: true })
            scope.listen(window, 'scroll', scroll, true)
            scope.listen(window, 'resize', resize)
            scope.listen(window.visualViewport, 'scroll', resize, { passive: true })
            scope.listen(window.visualViewport, 'resize', resize, { passive: true })
        } catch (error) {
            unbindPositionListeners(entry)
            throw error
        }
    }
    return {
        schedulePositionUpdate, unbindPositionListeners, bindPositionListeners,
        dispose() {
            if (disposed) return
            disposed = true
            if (frame !== null) cancelFrame(frame)
            frame = null
            dirty.clear()
            for (const entry of [...scopes.keys()]) unbindPositionListeners(entry)
        },
    }
}
