/** Visible page bounds: a body portal must not cover the sticky application header. */
export function resolveOverlayViewport(anchor, view = anchor?.ownerDocument?.defaultView, padding = 16) {
    const height = view?.innerHeight ?? 0
    let top = padding
    const document = anchor?.ownerDocument
    // A modal is its own layer above the page header.
    if (!anchor?.closest?.('dialog[open], [role="dialog"][aria-modal="true"], .fi-modal')) {
        for (const header of document?.querySelectorAll?.('.fi-topbar-ctn, .fi-topbar, [data-fff-overlay-obstruction]') ?? []) {
            const style = view?.getComputedStyle?.(header)
            const rect = header.getBoundingClientRect()
            if ((style?.position === 'sticky' || style?.position === 'fixed')
                && rect.top <= padding && rect.bottom > 0 && rect.height < height / 2) {
                top = Math.max(top, rect.bottom + padding)
            }
        }
    }
    return { top, bottom: Math.max(top, height - padding), height: Math.max(0, height - padding - top) }
}

export function resolveOverlayBaseLayer(anchor) {
    return anchor?.closest?.('dialog[open], [role="dialog"][aria-modal="true"], .fi-modal') ? 60 : 20
}
