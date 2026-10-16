/** A detached panel retains its user position while staying reachable after resize. */
export function clampFloatingPosition(point, size, viewport, padding = 8) {
    if (!Number.isFinite(point?.left) || !Number.isFinite(point?.top)) return null
    const left = (viewport.offsetLeft ?? 0) + padding
    const top = (viewport.offsetTop ?? 0) + padding
    return {
        left: Math.round(Math.max(left, Math.min(point.left, left + Math.max(0, viewport.width - padding * 2 - size.width)))),
        top: Math.round(Math.max(top, Math.min(point.top, top + Math.max(0, viewport.height - padding * 2 - size.height)))),
    }
}

export function resolveFloatingPanelPosition(point, panel, view, size = null) {
    const position = clampFloatingPosition(point, size ?? {width:panel.offsetWidth, height:panel.offsetHeight},
        view.visualViewport ?? {width:view.innerWidth, height:view.innerHeight})
    // A trigger morph grows down from its anchor. Its owner brings the footer
    // into view; vertical clamping would detach that surface from the trigger.
    if (position && point.followAnchor === true) position.top = point.top
    return position
}
