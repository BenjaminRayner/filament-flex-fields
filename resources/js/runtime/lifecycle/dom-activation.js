import { customSurfacesFor } from '../surfaces/document-surfaces.js'

const NativeElement = globalThis.Element

// Include body/modal boundaries so closest() cannot jump past an ancestor
// that the activation walk intentionally stops at. All other selectors mirror
// explicit suspension predicates; arbitrary application classes do not qualify.
const ACTIVATION_OWNER = 'body, .fi-modal, [hidden], [aria-hidden="true"], [data-fff-asset-active="false"], .hidden, .invisible, [style], details, .fi-sc-tabs-tab, .fi-sc-wizard-step, .fff-segment-tabs__panel, .fi-section-content-ctn'

export function findAssetActivationOwner(owner) {
    return NativeElement && owner instanceof NativeElement ? owner.closest(ACTIVATION_OWNER) : owner
}

export function isHiddenAssetOwner(owner) {
    // Native selectors avoid repeatedly crossing into DOMTokenList and
    // CSSStyleDeclaration for every ancestor of every consumer. Read the
    // current DOM on each call; no visibility result survives a mutation.
    if (NativeElement && owner instanceof NativeElement) {
        // Native hidden/style/class properties reflect attributes. A bare
        // layout ancestor cannot carry any of the suspension declarations.
        if (!owner.hasAttributes()) return false
        return owner.matches('[hidden], [aria-hidden="true"], [data-fff-asset-active="false"], .hidden, .invisible')
            || (owner.hasAttribute('style') && (owner.style?.display === 'none' || owner.style?.visibility === 'hidden'))
    }
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
