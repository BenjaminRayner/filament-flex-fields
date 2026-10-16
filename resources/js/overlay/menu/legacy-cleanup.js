import { cancelMenuCloseAnimation } from './animation.js'
import { clearOverlaySheetInlineStyles, removeSheetChrome } from './presentation.js'
import { removeOverlayBackdrop } from '../../core/overlay-backdrop.js'
import { closeOverlayPanel, releaseOverlayExclusive } from '../../core/overlay-runtime-bridge.js'
import { unlockOverlaySheetScroll, OVERLAY_SHEET_OWNERS_KEY } from '../../core/overlay-sheet-scroll-lock.js'
/**
 * Explicit compatibility escape hatch for application-owned legacy menus.
 * Managed pickers never call this global sweep: it cannot preserve ancestry.
 *
 * @param {HTMLElement | null} ownMenu
 */
export function forceHideForeignSelectMenus(ownMenu = null) {
    if (typeof document === 'undefined' || typeof document.querySelectorAll !== 'function') {
        return
    }

    const menus = document.querySelectorAll(
        [
            '.fff-select-headless-menu.is-open',
            '.fff-select-headless-menu.is-closing',
            '.fff-teleported-menu.is-open',
            '.fff-teleported-menu.is-closing',
        ].join(', '),
    )

    for (const menu of menus) {
        if (ownMenu && menu === ownMenu) {
            continue
        }

        cancelMenuCloseAnimation(menu)
        menu.classList.remove('is-open', 'is-closing')

        try {
            const data = typeof window !== 'undefined'
                ? window.Alpine?.$data?.(menu)
                : null

            if (data && typeof data.closeTeleportedMenuImmediate === 'function') {
                data.closeTeleportedMenuImmediate()
            }
        } catch {
            // Foreign / destroyed Alpine scopes — visual hide above is enough.
        }
    }
}

/**
 * Emergency cleanup when Livewire morph removes a select host before Alpine destroy.
 * Safe to call repeatedly; only clears open teleported select menus + sheet chrome.
 *
 * @param {ParentNode | null | undefined} root
 */
export function emergencySelectOverlayCleanup(root = typeof document !== 'undefined' ? document : null) {
    if (! root || typeof root.querySelectorAll !== 'function') {
        return
    }

    const menus = root.querySelectorAll?.(
        '.fff-select-headless-menu.is-open, .fff-select-headless-menu.is-closing, .fff-teleported-menu.is-open, .fff-teleported-menu.is-closing, .fff-overlay-sheet.is-open',
    ) ?? []

    for (const menu of menus) {
        cancelMenuCloseAnimation(menu)
        menu.classList.remove(
            'is-open',
            'is-closing',
            'fff-teleported-menu--sheet',
            'fff-teleported-menu--panel',
        )
        clearOverlaySheetInlineStyles(menu)
        removeSheetChrome(menu)

        const overlayId = menu.getAttribute?.('data-fff-overlay-id')
            ?? menu.dataset?.fffOverlayId
            ?? null

        if (overlayId) {
            removeOverlayBackdrop(document, overlayId)
            closeOverlayPanel(overlayId)
            releaseOverlayExclusive(overlayId)
        }
    }

    // Orphan sheet scroll lock: if no open sheet remains, unlock body.
    if (typeof document !== 'undefined'
        && ! document.querySelector('.fff-teleported-menu--sheet.is-open, .fff-overlay-sheet.is-open')) {
        // Owner leases retire only their detached/closed panels. An emergency
        // sweep must never decrement a live parent's independent scroll claim.
        if (document[OVERLAY_SHEET_OWNERS_KEY]) document[OVERLAY_SHEET_OWNERS_KEY].prune()
        else unlockOverlaySheetScroll(document)
    }
}

let selectOverlayMorphHookRegistered = false

/**
 * Register once: when Livewire is about to morph away a subtree that still
 * owns an open teleported select menu, force-hide overlays (Alpine destroy may not run).
 */
export function registerSelectOverlayMorphCleanup() {
    if (selectOverlayMorphHookRegistered || typeof window === 'undefined') {
        return
    }

    const Livewire = window.Livewire

    if (! Livewire || typeof Livewire.hook !== 'function') {
        return
    }

    selectOverlayMorphHookRegistered = true

    Livewire.hook('morph.updating', ({ el, component, skip, toEl }) => {
        void component
        void skip
        void toEl

        if (! (el instanceof Element)) {
            return
        }

        // Field shell removed, or wire:ignore island replaced — sweep open menus under el.
        if (
            el.matches?.('.fff-select-field, .fff-select-field__shell, [data-fff-select-field]')
            || el.querySelector?.('.fff-select-headless-menu.is-open, .fff-teleported-menu.is-open')
        ) {
            emergencySelectOverlayCleanup(el)
        }
    })
}

