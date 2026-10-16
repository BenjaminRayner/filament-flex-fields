import { changesFilamentActivation as changesAssetActivation } from '../filament/activation-mutation.js'
import { createOwnerTreeIndex } from '../owners/owner-tree-index.js'
import { getRuntimeState } from '../runtime-state.js'
import { customSurfacesFor } from '../surfaces/document-surfaces.js'
import { isHiddenAssetOwner } from './dom-activation.js'

/** Asset visibility is a surface lifecycle, not intersection with the viewport.
 * Fields below the fold stay usable; closed tabs and modal shells have no demand.
 * A suspended parent modal keeps its demand until it is actually closed.
 */
export function isAssetElementActive(element, { document = element?.ownerDocument ?? globalThis.document, surfaces = null, allowDetached = false } = {}) {
    if (!element || (!allowDetached && element.isConnected === false)) return false
    const custom = customSurfacesFor(document)
    if (custom?.isActiveFor(element) === false) return false
    const modal = element.closest?.('.fi-modal')
    const retainedModal = modal && (surfaces
        ? surfaces.modalOpenStack.includes(surfaces.resolveModalOwnerKey(modal))
        : (globalThis.window?.FffAssetInjector?.getModalOpenStack?.() ?? []).includes(`modal:${modal.id}`))
    if (modal && !retainedModal && !modal.classList?.contains('fi-modal-open')) return false
    // With a registry, closing wins over the lingering transition class.
    if (modal && surfaces && !retainedModal) return false

    let owner = element.hasAttribute?.('data-fff-asset-batch') ? element.parentElement : element
    while (owner && owner !== modal && owner !== document?.body) {
        const suspendedWindow = retainedModal && (owner.classList?.contains('fi-modal-window') || owner.classList?.contains('fi-modal-window-ctn'))
        if (!suspendedWindow && !custom?.retainsRoot(owner)) {
            if (isHiddenAssetOwner(owner)) return false
            if ((owner.classList?.contains('fi-sc-tabs-tab') || owner.classList?.contains('fi-sc-wizard-step')) && !owner.classList.contains('fi-active')) return false
            if (owner.classList?.contains('fff-segment-tabs__panel') && !owner.classList.contains('is-active')) return false
        }
        if (owner.classList?.contains('fi-section-content-ctn') && owner.parentElement?.classList?.contains('fi-collapsed')) return false
        if (owner.tagName === 'DETAILS' && !owner.open && !element.closest?.('summary')) return false
        owner = owner.parentElement
    }
    return true
}

/** One observer per document, shared by both bootstrap and runtime bundles.
 * Waiting fields do not import modules or run Alpine factories before activation.
 */
export function waitForAssetActivation(element, { signal } = {}) {
    const document = element?.ownerDocument ?? globalThis.document
    const active = () => globalThis.window?.FffAssetInjector?.isElementActive?.(element) ?? isAssetElementActive(element, { document })
    if (signal?.aborted || element?.isConnected === false) return Promise.resolve(false)
    if (active()) return Promise.resolve(true)
    const state = getRuntimeState()
    state.activationWaiters ??= new WeakMap()
    let group = state.activationWaiters.get(document)
    if (!group) {
        const waiters = new Set(), index = createOwnerTreeIndex()
        const check = mutations => {
            if (!Array.isArray(mutations)) { for (const waiter of [...waiters]) waiter(); return }
            const affected = new Set()
            for (const mutation of mutations) {
                if (mutation.type === 'attributes' && ['class', 'style'].includes(mutation.attributeName) && !changesAssetActivation(mutation)) continue
                const roots = mutation.type === 'attributes' ? [mutation.target] : [...mutation.addedNodes, ...mutation.removedNodes]
                for (const root of roots) for (const waiter of index.within(root)) affected.add(waiter)
            }
            for (const waiter of affected) waiter()
        }
        const Observer = document?.defaultView?.MutationObserver ?? globalThis.window?.MutationObserver
        const observer = typeof Observer === 'function' ? new Observer(check) : null
        observer?.observe(document.body, { subtree: true, childList: true, attributes: true, attributeOldValue: true,
            attributeFilter: ['class', 'style', 'hidden', 'aria-hidden', 'data-fff-asset-active', 'open'] })
        const observeBody = () => {
            observer?.disconnect()
            if (document.body) observer?.observe(document.body, { subtree: true, childList: true, attributes: true, attributeOldValue: true,
                attributeFilter: ['class', 'style', 'hidden', 'aria-hidden', 'data-fff-asset-active', 'open'] })
            check()
        }
        document?.addEventListener?.('livewire:navigated', observeBody)
        const modalOpened = event => {
            const modal = document.getElementById?.(event?.detail?.id) ?? event?.target?.closest?.('.fi-modal')
            if (modal) for (const waiter of index.within(modal)) waiter()
            else check()
        }
        document?.addEventListener?.('x-modal-opened', modalOpened)
        group = { waiters, index, observer, check, observeBody, modalOpened }
        state.activationWaiters.set(document, group)
    }
    if (group.waiters.size >= 4096) return Promise.reject(Error('FLEX activation waiter budget exhausted.'))
    return new Promise(resolve => {
        let finished = false
        const finish = value => {
            if (finished) return
            finished = true
            group.waiters.delete(check)
            group.index.remove(check)
            signal?.removeEventListener('abort', abort)
            if (!group.waiters.size) {
                group.observer?.disconnect()
                document?.removeEventListener?.('x-modal-opened', group.modalOpened)
                document?.removeEventListener?.('livewire:navigated', group.observeBody)
                if (state.activationWaiters.get(document) === group) state.activationWaiters.delete(document)
            }
            resolve(value)
        }
        const abort = () => finish(false)
        const check = () => {
            if (signal?.aborted || element.isConnected === false) finish(false)
            else if (active()) finish(true)
            else group.index.bind(check, element)
        }
        group.waiters.add(check)
        group.index.bind(check, element)
        signal?.addEventListener('abort', abort, { once: true })
        check()
    })
}
