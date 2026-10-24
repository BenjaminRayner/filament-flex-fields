const owners = new WeakMap()
export function colorRuntime(field) {
    const owner = field.__colorOwner ?? field.$el ?? field
    if (!owners.has(owner)) owners.set(owner, { frame: null, epoch: 0, panelEpoch: 0, panelPending: null, panelSurface: null, panelExitCleanup: null, cleanup: [], drag: null, disposed: false })
    return owners.get(owner)
}
export function cancelColorDrag(field) {
    const r = colorRuntime(field)
    if (r.frame != null) cancelAnimationFrame(r.frame)
    r.frame = null
    const drag = r.drag
    r.drag = null; r.pendingChannels = null
    if (drag && field.$refs.saturationArea?.hasPointerCapture?.(drag.id)) field.$refs.saturationArea.releasePointerCapture(drag.id)
}
