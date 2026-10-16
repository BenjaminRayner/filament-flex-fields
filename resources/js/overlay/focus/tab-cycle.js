/** Share focus geometry; keyboard selection/virtual row ownership stays in each picker. */
export function getOverlayFocusables(menu) {
    if (!menu) return []
    const view = menu.ownerDocument?.defaultView
    return [...menu.querySelectorAll('button, [href], input, select, textarea, [tabindex]')].filter(element =>
        element.tabIndex >= 0 && !element.matches(':disabled')
        && !element.closest('[inert], [hidden], [aria-hidden="true"]')
        && element.getClientRects().length > 0
        && (!view || view.getComputedStyle(element).visibility !== 'hidden'))
}

export function cycleOverlayTab(event, menu) {
    if (event.key !== 'Tab' || event.defaultPrevented) return false
    const focusables = getOverlayFocusables(menu)
    if (!focusables.length) return false
    const index = focusables.indexOf(menu.ownerDocument.activeElement)
    const next = event.shiftKey
        ? (index <= 0 ? focusables.length - 1 : index - 1)
        : (index >= focusables.length - 1 ? 0 : index + 1)
    event.preventDefault()
    focusables[next].focus({ preventScroll: true })
    return true
}
