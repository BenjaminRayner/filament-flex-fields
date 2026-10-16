/** The thumbnail and its chrome share one reversible presentation transition. */
const leases = new WeakMap()

export function hideViewerOrigin(thumb) {
    const existing = leases.get(thumb)
    const host = thumb.closest?.('.fff-flex-file-upload__avatar-shell,.fff-flex-file-upload__tile,.fff-flex-file-upload__card')
    const attribute = 'data-fff-viewer-origin-active'
    const state = existing ?? { count: 0, visibility: thumb.style.visibility, host, previous: host?.getAttribute(attribute) }
    state.count++
    leases.set(thumb, state)
    thumb.style.visibility = 'hidden'
    host?.setAttribute(attribute, 'true')
    let restored = false
    return () => {
        if (restored) return
        restored = true
        if (--state.count > 0) return
        leases.delete(thumb)
        thumb.style.visibility = state.visibility
        if (state.host) state.previous == null ? state.host.removeAttribute(attribute) : state.host.setAttribute(attribute, state.previous)
    }
}
