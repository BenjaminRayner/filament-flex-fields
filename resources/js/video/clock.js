/** One owned clock: animation frames only while custom controls are visible. */
export function createVideoClock(component, runtime) {
    let lastSync = -Infinity
    const runnable = () => !runtime.disposed && runtime.ownerActive !== false && component.playing && !component.$el.ownerDocument.hidden
    const animated = () => component.showControls && !component.nativeControls && !component.facadeOnly && component.panelVisible
    const queue = () => runtime.schedule('clock', tick, animated() ? undefined : 500)
    const tick = frameTime => {
        if (!runnable()) return
        const now = Number.isFinite(frameTime) ? frameTime : component.$el.ownerDocument.defaultView.performance.now()
        if (animated()) component.paintProgress()
        if (now - lastSync >= (animated() ? 100 : 500)) { lastSync = now; component.syncPlayback() }
        queue()
    }
    return { update() { runtime.cancel('clock'); if (runnable()) queue() } }
}
