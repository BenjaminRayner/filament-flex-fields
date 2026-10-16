
export function ensureSheetChrome(menu) {
    if (! menu) {
        return
    }

    const existing = [...menu.querySelectorAll('[data-fff-overlay-handle], .fff-overlay-sheet__handle')]

    if (existing.length > 0) {
        const [keep, ...extras] = existing

        for (const node of extras) {
            node.remove()
        }

        if (keep instanceof HTMLElement && ! keep.querySelector('.fff-overlay-sheet__handle-bar')) {
            const bar = document.createElement('span')

            bar.className = 'fff-overlay-sheet__handle-bar'
            keep.appendChild(bar)
        }

        return
    }

    const handle = document.createElement('div')
    const bar = document.createElement('span')

    handle.className = 'fff-overlay-sheet__handle'
    handle.dataset.fffOverlayHandle = 'true'
    handle.setAttribute('aria-hidden', 'true')
    bar.className = 'fff-overlay-sheet__handle-bar'
    handle.appendChild(bar)
    menu.insertBefore(handle, menu.firstChild)
}

export function applyOverlayPresentation(menu, mode) {
    if (! menu) {
        return
    }

    menu.classList.toggle('fff-teleported-menu--sheet', mode === 'sheet')
    menu.classList.toggle('fff-teleported-menu--panel', mode !== 'sheet')
    menu.classList.toggle('fff-overlay-sheet', mode === 'sheet')
    menu.classList.toggle('fff-overlay-panel', mode !== 'sheet')

    if (menu.dataset) {
        menu.dataset.fffOverlayPresentation = mode

        if (mode === 'sheet') {
            menu.dataset.fffOverlaySheet = 'true'
        } else {
            delete menu.dataset.fffOverlaySheet
            delete menu.dataset.fffSheetPreferredHeight
        delete menu.dataset.fffSheetFittedHeight
            delete menu.dataset.fffOverlaySnap
        }
    }

    if (mode === 'sheet') {
        ensureSheetChrome(menu)

        return
    }

    // Panel mode must not keep sheet peek height / bottom pin from a prior
    // sheet open (or a mode flip) — that leaves a giant empty desktop menu.
    clearOverlaySheetInlineStyles(menu)
    removeSheetChrome(menu)
}

/**
 * @param {HTMLElement | null | undefined} menu
 */
export function clearOverlaySheetInlineStyles(menu) {
    if (! menu?.style || typeof menu.style.removeProperty !== 'function') {
        return
    }

    for (const property of [
        'height',
        'max-height',
        'min-height',
        'bottom',
        'left',
        'right',
        'inset-inline',
        'width',
        'min-width',
        'max-width',
        'top',
        'transform',
        'transition',
        'visibility',
        'opacity',
        'pointer-events',
    ]) {
        menu.style.removeProperty(property)
    }

    menu.style.removeProperty('--fff-overlay-sheet-max-height')

    if (menu.dataset) {
        delete menu.dataset.fffSheetPreferredHeight
        delete menu.dataset.fffSheetFittedHeight
        delete menu.dataset.fffOverlaySnap
        delete menu.dataset.fffSheetLayoutViewport
        delete menu.dataset.fffOverlaySheetMinHeight
    }

    // Force panel glass to re-apply after leaving sheet (opaque / no-blur) chrome.
    delete menu.__fffMenuThemeKey
}

/**
 * @param {HTMLElement | null | undefined} menu
 */
export function removeSheetChrome(menu) {
    if (! menu?.querySelectorAll) {
        return
    }

    menu.querySelectorAll('[data-fff-overlay-handle], .fff-overlay-sheet__handle').forEach((node) => {
        node.remove()
    })
}

/**
 * @param {HTMLElement | null | undefined} menu
 * @returns {boolean}
 */
export function menuIsSheet(menu) {
    return Boolean(
        menu?.classList?.contains('fff-teleported-menu--sheet')
        || menu?.classList?.contains('fff-overlay-sheet'),
    )
}
