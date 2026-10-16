import { customSurfacesFor } from '../surfaces/document-surfaces.js'

export function isHiddenAssetOwner(owner) {
    return owner.hidden || owner.getAttribute?.('aria-hidden') === 'true'
        || owner.getAttribute?.('data-fff-asset-active') === 'false'
        || owner.style?.display === 'none' || owner.style?.visibility === 'hidden'
        || owner.classList?.contains('hidden') || owner.classList?.contains('invisible')
}

/** DOM activation uses explicit lifecycle and visibility, never viewport intersection. */
export function isDomAssetElementActive(element, { document = element?.ownerDocument, allowDetached = false } = {}) {
    if (!element || (!allowDetached && element.isConnected === false)) return false
    const custom = customSurfacesFor(document)
    if (custom?.isActiveFor(element) === false) return false
    let owner = element.hasAttribute?.('data-fff-asset-batch') ? element.parentElement : element
    while (owner && owner !== document?.body) {
        if (!custom?.retainsRoot(owner) && isHiddenAssetOwner(owner)) return false
        if (owner.tagName === 'DETAILS' && !owner.open && !element.closest?.('summary')) return false
        owner = owner.parentElement
    }
    return true
}
