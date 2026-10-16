export function clampHorizontalCenter(trigger, popup, bounds, { sideOffset = 12, boundaryOffset = 8 } = {}) {
    const half = popup.width / 2, max = bounds.right - boundaryOffset - half
    const centerX = Math.max(bounds.left + boundaryOffset + half, Math.min(trigger.left + trigger.width / 2, max))
    return { left: centerX - half, top: trigger.top - popup.height - sideOffset, centerX }
}
export function getAvailableMenuHeight(trigger, bounds, { side = 'top', sideOffset = 8, boundaryOffset = 8 } = {}) {
    return Math.max(0, side === 'top' ? trigger.top - bounds.top - sideOffset - boundaryOffset : bounds.bottom - trigger.bottom - sideOffset - boundaryOffset)
}
export function positionTopPopup(popup, trigger, boundary, options = {}) {
    if (!popup || !trigger || !boundary) return
    const rect = popup.getBoundingClientRect(), bounds = boundary.getBoundingClientRect()
    const position = clampHorizontalCenter(trigger.getBoundingClientRect(), { width: popup.offsetWidth || rect.width, height: popup.offsetHeight || rect.height }, bounds, options)
    Object.assign(popup.style, { position: 'fixed', left: `${position.left}px`, top: `${Math.max(bounds.top + (options.boundaryOffset ?? 8), position.top)}px`, right: 'auto', bottom: 'auto', transform: 'none' })
}
