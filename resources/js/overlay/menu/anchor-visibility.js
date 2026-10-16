/** A teleported menu cannot survive a hidden or removed control owner. */
export function isMenuAnchorAvailable(anchor) {
    if (!anchor || anchor.isConnected === false) return false
    if (anchor.closest?.('[hidden], [inert], [aria-hidden="true"]')) return false
    if (anchor.getClientRects && anchor.getClientRects().length === 0) return false
    return true
}

/** Observe the owner chain only; option recycling/scrolling cannot trigger this observer. */
export function observeMenuAnchor(anchor, check) {
    const Observer = anchor?.ownerDocument?.defaultView?.MutationObserver
    if (!Observer) return () => {}
    const observer = new Observer(check)
    for (let node = anchor; node?.nodeType === 1; node = node.parentElement) {
        observer.observe(node, { childList: true, attributes: true,
            attributeFilter: ['class', 'style', 'hidden', 'inert', 'aria-hidden'] })
    }
    return () => observer.disconnect()
}
