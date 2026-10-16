import { createOwnerTreeIndex } from '../owners/owner-tree-index.js'

/** Tracks nested Filament surfaces independently of transport and pending UI. */
export function createSurfaceRegistry({ document, customSurfaces = null }) {
    const modalOpenStack = []
    const anonymousModalKeys = new WeakMap()
    const modalRoots = new Map(), owners = createOwnerTreeIndex()
    let anonymousModalSequence = 0

    const resolveModalOwnerKey = (modal) => {
        if (!modal) {
            return null
        }

        if (typeof modal.id === 'string' && modal.id !== '') {
            const key = `modal:${modal.id}`
            modalRoots.set(key, modal); owners.bind(key, modal)
            return key
        }

        if (anonymousModalKeys.has(modal)) {
            const key = anonymousModalKeys.get(modal)
            modalRoots.set(key, modal); owners.bind(key, modal)
            return key
        }

        anonymousModalSequence += 1
        const key = `modal:anon-${anonymousModalSequence}`
        anonymousModalKeys.set(modal, key)
        modalRoots.set(key, modal); owners.bind(key, modal)

        return key
    }

    const resolveAssetOwnerKey = (root = document) => {
        if (!root || root === document) {
            return 'page'
        }

        for (let node = root; node; node = node.parentElement) {
            const customKey = customSurfaces?.keyForRoot(node)
            if (customKey) return customKey
            if (node.classList?.contains?.('fi-modal')) return resolveModalOwnerKey(node)
        }

        // Compatibility with framework diagnostic fixtures exposing closest() only.
        const modal = root.closest?.('.fi-modal')
        if (modal) return resolveModalOwnerKey(modal)

        return 'page'
    }

    const pushModalOpenStack = (ownerKey) => {
        if (!ownerKey || ownerKey === 'page') {
            return
        }

        // Re-opening the same modal (e.g. return from nested child) must not
        // duplicate the stack entry.
        if (modalOpenStack[modalOpenStack.length - 1] === ownerKey) {
            return
        }

        const existingIndex = modalOpenStack.indexOf(ownerKey)

        if (existingIndex >= 0) {
            modalOpenStack.splice(existingIndex, 1)
        }

        modalOpenStack.push(ownerKey)
    }

    const popModalOpenStack = (ownerKey = null) => {
        if (ownerKey) {
            const index = modalOpenStack.lastIndexOf(ownerKey)

            if (index >= 0) {
                modalOpenStack.splice(index, 1)
            }

            return ownerKey
        }

        const top = modalOpenStack.pop()

        return top ?? null
    }

    const resolveModalRoot = (event) => {
        const modalId = event?.detail?.id

        if (typeof modalId === 'string' && modalId !== '') {
            return document.getElementById(modalId)
        }

        // Stacked Filament actions: prefer the topmost open modal, not the first in DOM.
        const openModals = document.querySelectorAll('.fi-modal.fi-modal-open')

        if (openModals.length === 0) {
            return null
        }

        return openModals[openModals.length - 1]
    }

    const pruneDetachedModals = (root = null) => {
        const keys = root?.nodeType === 1 ? owners.within(root) : [...modalRoots.keys()]
        for (const key of keys) {
            const modal = modalRoots.get(key)
            if (!modal) continue
            const current = modal.id ? document.getElementById?.(modal.id) : modal
            if (current?.isConnected !== false && current) {
                modalRoots.set(key, current); owners.bind(key, current)
                continue
            }
            popModalOpenStack(key); modalRoots.delete(key); owners.remove(key)
        }
    }

    const discoverOpenModals = () => {
        for (const modal of document.querySelectorAll('.fi-modal.fi-modal-open')) {
            pushModalOpenStack(resolveModalOwnerKey(modal))
        }
    }

    return { getModalRoot: key => modalRoots.get(key) ?? null, dispose() { modalOpenStack.length = 0; modalRoots.clear(); owners.clear() }, pruneDetachedModals, discoverOpenModals, modalOpenStack, resolveModalOwnerKey, resolveAssetOwnerKey, pushModalOpenStack, popModalOpenStack, resolveModalRoot }
}
