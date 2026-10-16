import { resolveOwnedOverlayStack } from '../ownership/stacking.js'
import { createOverlayBackdrop, removeOverlayBackdrop, OVERLAY_SHEET_BACKDROP_Z_INDEX, OVERLAY_SHEET_PANEL_Z_INDEX } from '../../core/overlay-backdrop.js'
import { bindOverlaySheetDismiss, OVERLAY_SHEET_GEOMETRY_EVENT } from '../../core/overlay-sheet-dismiss.js'
import { lockOverlaySheetScroll, unlockOverlaySheetScroll, bindOverlaySheetVisualViewport, syncOverlaySheetToVisualViewport } from '../../core/overlay-sheet-scroll-lock.js'
import { menuIsSheet, clearOverlaySheetInlineStyles, removeSheetChrome } from './presentation.js'
import { acquireSheetScrollLease } from '../ownership/sheet-scroll-leases.js'
import { createResourceScope } from '../../runtime/lifecycle/resource-scope.js'
/**
 * @param {{ __fffSheetVisualViewportCleanup?: (() => void) | null, __fffSheetDismissCleanup?: (() => void) | null, __fffSheetScrollRelease?: (() => void) | null, __fffSheetScrollLocked?: boolean }} host
 */
export function releaseOverlaySheetEnvironment(host) {
    const viewport = host.__fffSheetVisualViewportCleanup
    const dismiss = host.__fffSheetDismissCleanup
    const scroll = host.__fffSheetScrollRelease
        ?? (host.__fffSheetScrollLocked ? () => unlockOverlaySheetScroll(document) : null)
    host.__fffSheetVisualViewportCleanup = host.__fffSheetDismissCleanup = host.__fffSheetScrollRelease = null
    host.__fffSheetScrollLocked = false
    // Detach ownership before callbacks; reentrant or failed cleanup must not
    // run twice or prevent the remaining listeners/lock from being released.
    const scope = createResourceScope()
    for (const cleanup of [scroll, dismiss, viewport]) if (typeof cleanup === 'function') scope.defer(cleanup)
    scope.dispose()
}

export function createMenuSheetEnvironmentMixin({ openKey }) {
    return {
        syncOpenOverlaySheetEnvironment(mode, menu = null, options = {}) {
            if (! this[openKey] || typeof document === 'undefined') {
                return
            }

            const bindDismiss = options.bindDismiss !== false
            const overlayId = this.resolveMenuOverlayId()
            const panel = menu ?? this.resolveMenuElement()

            if (mode === 'sheet') {
                if (! this.__fffSheetScrollLocked) {
                    if (panel?.ownerDocument && this.resolveMenuTriggerRef()?.ownerDocument) {
                        const doc = panel.ownerDocument
                        this.__fffSheetScrollRelease = acquireSheetScrollLease({
                            document: doc,
                            anchor: () => this.resolveMenuTriggerRef(),
                            panel: () => this.resolveMenuElement() ?? panel,
                            current: () => this[openKey] && !this.__fffPickerDisposed,
                        })
                    } else {
                        // Compatibility for older imperative hosts without a DOM anchor.
                        lockOverlaySheetScroll(document)
                    }
                    this.__fffSheetScrollLocked = true
                }

                if (panel && ! this.__fffSheetVisualViewportCleanup) {
                    this.__fffSheetVisualViewportCleanup = bindOverlaySheetVisualViewport(panel, window)
                }

                if (panel) {
                    const stack = resolveOwnedOverlayStack(panel, this.resolveMenuTriggerRef(), OVERLAY_SHEET_PANEL_Z_INDEX, OVERLAY_SHEET_BACKDROP_Z_INDEX)
                    syncOverlaySheetToVisualViewport(panel, window)
                    // Numeric z-index — CSS var strings + first-paint "auto" used to put
                    // the dimmer above the sheet on the first mobile open.
                    if (typeof panel.style?.setProperty === 'function') {
                        panel.style.setProperty('z-index', String(stack.panel), 'important')
                    } else if (panel.style) {
                        panel.style.zIndex = String(stack.panel)
                    }

                    const backdrop = createOverlayBackdrop(document, overlayId, {
                        zIndex: stack.backdrop,
                        beforeElement: panel,
                        onDismiss: () => {
                            if (this.__fffOverlayMode === 'sheet' || menuIsSheet(this.resolveMenuElement())) {
                                this.closeTeleportedMenu()

                                return
                            }

                            if (typeof this.closeTeleportedMenuImmediate === 'function') {
                                this.closeTeleportedMenuImmediate()

                                return
                            }

                            this[openKey] = false
                        },
                    })

                    if (stack.nested && backdrop?.dataset) backdrop.dataset.fffDropdownParent = stack.ownerId

                    if (
                        bindDismiss
                        && ! this.__fffSheetDismissCleanup
                        && typeof panel.addEventListener === 'function'
                    ) {
                        const onSheetGeometry = () => {
                            if (typeof this.onOverlaySheetGeometry === 'function') {
                                this.onOverlaySheetGeometry()

                                return
                            }

                            if (typeof this.measureVirtualListViewport === 'function') {
                                this.measureVirtualListViewport()
                            }
                        }

                        panel.addEventListener(OVERLAY_SHEET_GEOMETRY_EVENT, onSheetGeometry)
                        this.__fffSheetDismissCleanup = (() => {
                            const dismissCleanup = bindOverlaySheetDismiss({
                                panel,
                                onDismiss: () => {
                                    if (this.__fffSheetClosing || ! this[openKey]) {
                                        return
                                    }

                                    // Exit transform already finished in bindOverlaySheetDismiss.
                                    this.__fffSheetClosing = true
                                    removeOverlayBackdrop(document, overlayId)

                                    if (panel) {
                                        panel.classList.remove(
                                            'is-open',
                                            'is-closing',
                                            'is-dismissing',
                                            'fff-overlay-sheet',
                                            'fff-teleported-menu--sheet',
                                        )
                                        delete panel.dataset?.fffOverlaySheet
                                        delete panel.dataset?.fffOverlayPresentation
                                        clearOverlaySheetInlineStyles(panel)
                                        removeSheetChrome(panel)

                                        if (typeof panel.style?.setProperty === 'function') {
                                            panel.style.setProperty('visibility', 'hidden', 'important')
                                            panel.style.setProperty('max-height', '0', 'important')
                                            panel.style.setProperty('opacity', '0', 'important')
                                            panel.style.setProperty('pointer-events', 'none', 'important')
                                            panel.style.setProperty('box-shadow', 'none', 'important')
                                        }
                                    }

                                    this.__fffSheetClosing = false
                                    this[openKey] = false
                                },
                            })

                            return () => {
                                panel.removeEventListener(OVERLAY_SHEET_GEOMETRY_EVENT, onSheetGeometry)
                                dismissCleanup()
                            }
                        })()
                    }
                }

                return
            }

            removeOverlayBackdrop(document, overlayId)
            releaseOverlaySheetEnvironment(this)
        },

    }
}
