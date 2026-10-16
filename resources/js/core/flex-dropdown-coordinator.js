import { documentDropdownControllers, documentDropdownOwnership } from '../overlay/ownership/document-controllers.js'

let dropdownOwnerSequence = 0

function registry() {
    return documentDropdownControllers(typeof document === 'undefined' ? null : document)
}

/** @type {WeakMap<Element, () => void>} */
const elementBindings = new WeakMap()

/** @type {WeakMap<Element, string>} */
const dropdownOwnerIds = new WeakMap()

let alpineStoreInitialized = false

export function createDropdownOwnerId(prefix = 'fff-dropdown') {
    if (typeof document !== 'undefined') return `${prefix}-${++documentDropdownOwnership(document).sequence}`
    dropdownOwnerSequence += 1

    return `${prefix}-${dropdownOwnerSequence}`
}

function resolveOwnerKey(target) {
    if (target instanceof Element) {
        return target
    }

    if (target?._pickerHostElement instanceof Element) return target._pickerHostElement
    if (target?.$el instanceof Element) {
        return target.$el
    }

    return null
}

export function resolveAlpineComponent(target) {
    if (target instanceof Element) {
        return window.Alpine?.$data(target) ?? null
    }

    if (target?.$el instanceof Element) {
        return window.Alpine?.$data(target.$el) ?? target
    }

    return target ?? null
}

export function resolveDropdownOwnerId(target, ownerIdPrefix = 'fff-dropdown') {
    const ownerKey = resolveOwnerKey(target)

    if (! ownerKey) {
        return null
    }

    const ids = typeof document === 'undefined' ? dropdownOwnerIds : documentDropdownOwnership(document).ids
    let ownerId = ids.get(ownerKey)

    if (! ownerId) {
        ownerId = createDropdownOwnerId(ownerIdPrefix)
        ids.set(ownerKey, ownerId)
    }

    return ownerId
}

/**
 * @param {object} component
 * @param {{ openKey?: string, closeMethod?: string|null }} options
 * @returns {import('./flex-dropdown-registry.js').FlexDropdownController}
 */
export function createFlexDropdownController(component, {
    openKey = 'menuOpen',
    closeMethod = null,
} = {}) {
    const ownerId = resolveDropdownOwnerId(component)
    let ancestor = resolveOwnerKey(component)?.closest('[data-fff-dropdown-owner]')
    while (ancestor && ancestor.dataset.fffDropdownOwner === ownerId) {
        ancestor = ancestor.parentElement?.closest('[data-fff-dropdown-owner]')
    }
    return {
        parentOwnerId: ancestor?.dataset.fffDropdownOwner ?? null,
        isOpen: () => Boolean(component?.[openKey]),
        close: () => {
            if (! component) {
                return
            }

            if (closeMethod && typeof component[closeMethod] === 'function') {
                component[closeMethod]()

                return
            }

            component._pickerCloseReason = 'displaced'
            component[openKey] = false
        },
    }
}

export function openExclusiveFlexDropdown(ownerId) {
    return registry().openExclusive(ownerId)
}

export function registerFlexDropdown(ownerId, controller) {
    return registry().register(ownerId, () => controller)
}

export function unregisterFlexDropdown(ownerId) {
    registry().unregister(ownerId)
}

export function bindFlexDropdown(target, {
    getController,
    ownerIdPrefix = 'fff-dropdown',
} = {}) {
    const ownerKey = resolveOwnerKey(target)

    if (! (ownerKey instanceof Element) || typeof getController !== 'function') {
        return () => {}
    }

    elementBindings.get(ownerKey)?.()

    const ownerId = resolveDropdownOwnerId(ownerKey, ownerIdPrefix)

    if (! ownerId) {
        return () => {}
    }

    const binding = { owner: ownerKey, unregister: registry().register(ownerId, getController) }

    const unbind = () => {
        if (!binding.owner) return
        binding.unregister()
        if (elementBindings.get(binding.owner) === unbind) elementBindings.delete(binding.owner)
        binding.owner = binding.unregister = null
    }

    elementBindings.set(ownerKey, unbind)

    return unbind
}

export function wireExclusiveFlexDropdown(component, {
    openKey = 'menuOpen',
    closeMethod = null,
    ownerIdPrefix = 'fff-dropdown',
} = {}) {
    const ownerKey = resolveOwnerKey(component)

    if (! (ownerKey instanceof Element)) {
        return () => {}
    }

    // Prior bind must be released before re-wire (Livewire remount / init twice).
    if (typeof component.__fffDropdownUnbind === 'function') {
        component.__fffDropdownUnbind()
        component.__fffDropdownUnbind = null
    }

    const binding = { release: bindFlexDropdown(ownerKey, {
        ownerIdPrefix,
        getController: () => createFlexDropdownController(component, {
            openKey,
            closeMethod,
        }),
    }), stopWatching: null, owner: ownerKey }

    const unbind = () => {
        if (!binding.owner) return
        const release = binding.release, stopWatching = binding.stopWatching
        binding.owner = binding.release = binding.stopWatching = null
        try { stopWatching?.() } finally { release?.() }
    }
    component.__fffDropdownUnbind = unbind

    try {
        binding.stopWatching = component.$watch(openKey, (open) => {
            if (! open || !binding.owner) {
                return
            }

            const ownerId = resolveDropdownOwnerId(binding.owner, ownerIdPrefix)

            if (ownerId) {
                openExclusiveFlexDropdown(ownerId)
            }
        })
    } catch (error) {
        unbind()
        component.__fffDropdownUnbind = null
        throw error
    }
    if (typeof binding.stopWatching !== 'function') binding.stopWatching = null

    return unbind
}

export function createExclusiveDropdownMixin({
    openKey = 'menuOpen',
    closeMethod = null,
    ownerIdPrefix = 'fff-dropdown',
} = {}) {
    return {
        wireExclusiveFlexDropdown() {
            wireExclusiveFlexDropdown(this, {
                openKey,
                closeMethod,
                ownerIdPrefix,
            })
        },
    }
}

function initAlpineOverlayStore() {
    if (alpineStoreInitialized || typeof window === 'undefined' || ! window.Alpine?.store) {
        return
    }

    alpineStoreInitialized = true

    window.Alpine.store('fffOverlays', {
        resolveOwnerId: resolveDropdownOwnerId,
        register(ownerId, controller) {
            return registry().register(ownerId, () => controller)
        },
        unregister(ownerId) {
            registry().unregister(ownerId)
        },
        open(ownerId) {
            registry().openExclusive(ownerId)
        },
    })
}

if (typeof window !== 'undefined') {
    document.addEventListener('alpine:init', initAlpineOverlayStore)

    if (window.Alpine?.store) {
        initAlpineOverlayStore()
    }
}

export { createFlexDropdownRegistry } from './flex-dropdown-registry.js'
export const flexDropdownRegistry = {
    register: (...args) => registry().register(...args),
    unregister: (...args) => registry().unregister(...args),
    openExclusive: (...args) => registry().openExclusive(...args),
    size: () => registry().size(),
}
