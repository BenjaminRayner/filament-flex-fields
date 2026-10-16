import { resolveOverlayBaseLayer } from '../geometry/viewport.js'

/** Keep nested panels and dimmers above their actual portal ancestor. */
export function resolveOwnedOverlayStack(panel, anchor, basePanel = 60, baseBackdrop = 50) {
    const owner = anchor?.closest?.('[data-fff-dropdown-owner]')
    const view = anchor?.ownerDocument?.defaultView
    const ancestorZ = owner && view?.getComputedStyle ? Number(view.getComputedStyle(owner).zIndex) : NaN
    if (!owner) return { panel: basePanel, backdrop: baseBackdrop, nested: false }
    // Inline owners have z-index:auto. They still own a nested branch, but
    // must not fall back to a global layer above the sticky application header.
    const parentLayer = Number.isFinite(ancestorZ) ? ancestorZ : resolveOverlayBaseLayer(anchor)
    const z = Math.max(basePanel, Math.min(2147483645, parentLayer) + 2)
    panel?.style?.setProperty?.('--fff-nested-overlay-z', String(z))
    return { panel: z, backdrop: z - 1, nested: true, ownerId: owner.dataset.fffDropdownOwner }
}
