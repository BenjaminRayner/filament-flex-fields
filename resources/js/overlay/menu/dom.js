
const OVERFLOW_SCROLL_RE = /(auto|scroll|overlay)/

/** Includes shadow content and owned child portals, without treating sibling menus as inside. */
export function isEventWithinMenuSurface(event, trigger, menu) {
    const path = event?.composedPath?.() ?? [event?.target]
    const owner = menu?.dataset?.fffDropdownOwner
    return path.some(node => node?.nodeType && (node === trigger || node === menu
        || trigger?.contains?.(node) || menu?.contains?.(node)
        || (owner && node.closest?.('[data-fff-dropdown-parent]')?.dataset?.fffDropdownParent === owner)))
}

function isDomNode(value) {
    return value != null && typeof value === 'object' && typeof value.nodeType === 'number'
}

function isInternalMultiSelectChipScroll(event, trigger) {
    if (! trigger || ! isDomNode(event?.target) || ! trigger.contains(event.target)) {
        return false
    }

    return Boolean(event.target.closest('.fi-select-input-value-badges-ctn'))
}

export function shouldSkipMenuScrollReposition(event, menu, trigger) {
    if (
        menu
        && isDomNode(event?.target)
        && menu.contains(event.target)
    ) {
        return true
    }

    return isInternalMultiSelectChipScroll(event, trigger)
}

export function resolveScrollableParents(element) {
    const parents = []
    let node = element?.parentElement

    while (node && node !== document.documentElement) {
        const style = window.getComputedStyle(node)

        if (OVERFLOW_SCROLL_RE.test(`${style.overflow}${style.overflowY}${style.overflowX}`)) {
            parents.push(node)
        }

        node = node.parentElement
    }

    return parents
}

/**
 * Resolve this instance's teleported menu panel.
 * Prefer an explicit DOM id / owner attribute so multiple SelectFields on one
 * page cannot steal each other's `$refs.headlessMenu` after x-teleport.
 *
 * @param {object} component
 * @param {string} menuRef
 * @returns {HTMLElement | null}
 */
export function resolveSearchableSelectMenuElement(component, menuRef = 'menuMenu') {
    const byRef = component?.$refs?.[menuRef] ?? null
    const expectedId = component?.menuDomId
        ? String(component.menuDomId)
        : null
    const owner = component?.componentKey ?? component?.statePath ?? null
    const ownerToken = owner != null && String(owner) !== ''
        ? String(owner)
        : null

    if (
        byRef
        && (! expectedId || byRef.id === expectedId)
        && (
            ! ownerToken
            || byRef.getAttribute?.('data-fff-select-menu-owner') === ownerToken
            || ! byRef.hasAttribute?.('data-fff-select-menu-owner')
        )
    ) {
        return byRef
    }

    const root = typeof document !== 'undefined' ? document : null

    if (expectedId && root?.getElementById) {
        const byId = root.getElementById(expectedId)

        if (byId) {
            return byId
        }
    }

    if (ownerToken && typeof root?.querySelector === 'function') {
        const escaped = typeof CSS !== 'undefined' && typeof CSS.escape === 'function'
            ? CSS.escape(ownerToken)
            : ownerToken.replace(/\\/g, '\\\\').replace(/"/g, '\\"')
        const byOwner = root.querySelector(
            `.fff-select-headless-menu[data-fff-select-menu-owner="${escaped}"]`,
        )

        if (byOwner) {
            return byOwner
        }
    }

    return byRef
}
