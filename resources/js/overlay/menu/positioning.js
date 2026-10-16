import { isMenuAnchorAvailable } from './anchor-visibility.js'
import { resolveOwnedOverlayStack } from '../ownership/stacking.js'
import { resolveOverlayViewport } from '../geometry/viewport.js'
import { resolveFloatingPanelPosition } from '../geometry/floating-position.js'
import { createSurfaceOpenSession } from '../surface-open-session.js'
import { resolveDropdownOwnerId } from '../../core/flex-dropdown-coordinator.js'
import { resolveSearchableSelectMenuElement } from './dom.js'
import { resolveOverlayMode } from '../../core/overlay-mode.js'
import { OVERLAY_SHEET_PANEL_Z_INDEX } from '../../core/overlay-backdrop.js'
import { fitOverlaySheetToContent } from '../../core/overlay-sheet-dismiss.js'
import { syncOverlaySheetToVisualViewport } from '../../core/overlay-sheet-scroll-lock.js'
import { resolveTeleportedMenuHorizontalLeft, resolveTeleportedMenuVerticalPlacement } from '../../core/teleported-menu-position.js'
import { setOverlayPanelMode, updateOverlayPanelPosition } from '../../core/overlay-runtime-bridge.js'
import { resolveTeleportedMenuZIndex } from '../../core/theme-utils.js'
import { menuIsSheet, applyOverlayPresentation } from './presentation.js'
import { applyTeleportedMenuTheme } from './theme.js'
import { revealTeleportedMenuPanel } from './animation.js'
import { menuPanelMotion } from './panel-motion.js'
export function createMenuPositioningMixin({ openKey, readyKey, ownerIdPrefix, triggerRef, menuRef, menuThemeVariant, minMenuWidth, matchTriggerWidth, menuGap }) {
    return {
        notifyMenuPositioned() {
            menuPanelMotion(this.resolveMenuElement())?.positioned?.()
        },
        resolveMenuOverlayId() {
            if (this.__fffMenuOverlayId) {
                return this.__fffMenuOverlayId
            }

            this.__fffMenuOverlayId = resolveDropdownOwnerId(this, `${ownerIdPrefix}-surface`)

            return this.__fffMenuOverlayId
        },

        resolveDropdownAlign() {
            return 'start'
        },

        resolveMenuElement() {
            return resolveSearchableSelectMenuElement(this, menuRef)
        },

        resolveMenuTriggerRef() {
            if (typeof this.resolveMenuTriggerElement === 'function') {
                return this.resolveMenuTriggerElement()
            }

            return this.$refs[triggerRef] ?? null
        },

        scheduleMenuPosition({ onAnchored = null } = {}) {
            // Exit animation owns transform classes — reposition/reveal would
            // strip `is-closing` and flash the sheet open again.
            this._surfaceOpenSession?.dispose()
            if (this.__fffSheetClosing || this.__fffPanelClosing || this.__fffPickerDisposed || !this[openKey]) return
            const generation = ++this._surfaceOpenGeneration
            const opening = createSurfaceOpenSession({
                // Alpine proxies assigned objects. Compare a scalar generation,
                // never the raw session against its reactive wrapper.
                isCurrent: () => this._surfaceOpenGeneration === generation && this[openKey]
                    && !this.__fffPickerDisposed && !this.__fffSheetClosing,
                nextTick: (callback) => this.$nextTick(callback),
            })
            this._surfaceOpenSession = opening

            const trigger = this.resolveMenuTriggerRef()
            const menu = this.resolveMenuElement()
            if (!isMenuAnchorAvailable(trigger)) {
                this.closeTeleportedMenuImmediate?.('anchor-hidden')
                return
            }
            // Imperative morph renderers have finished their complete content
            // before opening and own their first frame. Alpine/CSS dropdowns
            // retain the regular paint/closed-frame readiness sequence.
            const synchronousLayout = menuPanelMotion(menu)?.synchronousLayout === true
            const scheduleContent = callback => synchronousLayout
                ? this.$nextTick(() => { if (opening.current()) callback() })
                : opening.afterRender(callback)
            const hasPositionedBefore = menu?.__fffHasBeenPositioned === true && this[readyKey] === true

            // Same-turn unlock only when the panel was already shown (Livewire
            // remorph / dependsOn). First open must wait for Alpine list paint +
            // final anchor — otherwise phone/country menus flash wrong size/top
            // (content-sized width, below→above flip) before settling.
            this[readyKey] = hasPositionedBefore

            const attempt = (pass = 0) => {
                scheduleContent(() => {
                        const currentTrigger = this.resolveMenuTriggerRef()
                        const currentMenu = this.resolveMenuElement()

                        if ((! currentTrigger || ! currentMenu) && pass < 12) {
                            attempt(pass + 1)

                            return
                        }

                        if (! currentTrigger || ! currentMenu) {
                            onAnchored?.()

                            return
                        }

                        const sheetMode = (this.resolveMenuPresentation?.() ?? resolveOverlayMode(window)) === 'sheet'
                            || menuIsSheet(currentMenu)

                        const alreadyPaintedOpen = typeof currentMenu.classList?.contains === 'function'
                            && currentMenu.classList.contains('is-open')

                        // Skip reveal reflow when optimistic is-open is already on.
                        // Sheets never use glass reveal — playSheetEnter owns the slide.
                        const shouldReveal = ! sheetMode
                            && ! alreadyPaintedOpen

                        if (typeof this.measureVirtualListViewport === 'function') {
                            this.measureVirtualListViewport()
                        }

                        // Position while still hidden — first paint must already be final size/placement.
                        // An imperative renderer is positioned once by the
                        // runtime's onAnchored open, before its first frame.
                        // Unmanaged and Alpine surfaces retain local anchoring.
                        if (!synchronousLayout || !window.FffOverlayRuntime?.open) {
                            this.updateMenuPosition({ reveal: false, markReady: false })
                        }

                        if (currentMenu) {
                            currentMenu.__fffHasBeenPositioned = true
                        }

                        const finishAnchor = () => {
                            if (typeof this.measureVirtualListViewport === 'function') {
                                this.measureVirtualListViewport()
                            }

                            // Never glass-reveal while still :not(.is-positioned) — the closed
                            // scale/opacity frame would paint invisible, then the first visible
                            // frame would already be .is-open (fade-only / pop). Ready first.
                            // Overlay open (onAnchored) may nudge geometry — reveal only after that.
                            this.prepareAnchoredMenu?.()
                            onAnchored?.()
                            if (!opening.current()) return
                            this[readyKey] = true

                            if (this[openKey] && ! this.__fffSheetClosing && currentMenu) {
                                // Sheet enter is owned by playSheetEnter() after overlay chrome is applied.
                                if (! sheetMode) {
                                    currentMenu.classList.remove('is-closing')

                                    if (shouldReveal && !synchronousLayout) {
                                        // Paint one closed glass frame, then open (Select parity).
                                        void currentMenu.offsetWidth
                                        opening.frame(() => {
                                            if (! this[openKey] || this.__fffSheetClosing || menuIsSheet(currentMenu)) {
                                                return
                                            }

                                            currentMenu.classList.add('is-open')
                                        })
                                    } else {
                                        currentMenu.classList.add('is-open')
                                    }
                                }
                            }
                        }

                        if (shouldReveal && !synchronousLayout) {
                            opening.frame(finishAnchor)

                            return
                        }

                        finishAnchor()
                })
            }

            attempt()
        },

        updateMenuPosition({ reveal = false, markReady = true } = {}) {
            if (this.__fffSheetClosing || this.__fffPanelClosing) {
                return
            }

            const trigger = this.resolveMenuTriggerRef()
            const menu = this.resolveMenuElement()

            if (! trigger || ! menu) {
                return
            }

            // While the enter slide runs, skip re-anchoring — skeleton/Livewire
            // height thrash would stutter the transform animation.
            if (menu.dataset?.fffSheetEntering === 'true') {
                return
            }

            if (this.__fffOverlayManaged) {
                const managedOverlayId = this.resolveMenuOverlayId()
                const runtime = typeof window !== 'undefined' ? window.FffOverlayRuntime : null
                const hasManagedPanel = typeof runtime?.hasPanel === 'function'
                    ? runtime.hasPanel(managedOverlayId)
                    : false

                if (hasManagedPanel) {
                    // Live breakpoint wins — stale open-time mode / leftover sheet
                    // classes must not leave the menu pinned left:0 after desktop restore.
                    const desiredMode = (this.resolveMenuPresentation?.() ?? resolveOverlayMode(window))

                    this.__fffOverlayMode = desiredMode
                    applyOverlayPresentation(menu, desiredMode)
                    const positionedByMode = setOverlayPanelMode(managedOverlayId, desiredMode)
                    applyTeleportedMenuTheme(menu, { variant: menuThemeVariant })
                    this.syncOpenOverlaySheetEnvironment(desiredMode, menu)

                    if (desiredMode === 'sheet') {
                        menu.style.position = 'fixed'
                        menu.style.zIndex = String(resolveOwnedOverlayStack(menu, trigger, OVERLAY_SHEET_PANEL_Z_INDEX).panel)
                        menu.style.setProperty('z-index', String(resolveOwnedOverlayStack(menu, trigger, OVERLAY_SHEET_PANEL_Z_INDEX).panel), 'important')
                        menu.style.setProperty('left', '0', 'important')
                        menu.style.setProperty('right', '0', 'important')
                        menu.style.setProperty('inset-inline', '0', 'important')
                        menu.style.setProperty('width', '100%', 'important')
                        menu.style.setProperty('min-width', '0', 'important')
                        menu.style.setProperty('max-width', 'none', 'important')
                        menu.style.removeProperty('top')
                        menu.style.marginTop = '0'
                        menu.classList.remove('fff-teleported-menu--above', 'fff-teleported-menu--below')
                        syncOverlaySheetToVisualViewport(menu, window)

                        // Fitting reads the inner scroller, not the capped outer panel.
                        // Unchanged content emits no resize; keyboard retains its own clamp.

                        if (
                            this[openKey]
                            && menu.classList?.contains?.('is-open')
                            && menu.dataset?.fffSheetEntering !== 'true'
                        ) {
                            fitOverlaySheetToContent(menu, window)
                            syncOverlaySheetToVisualViewport(menu, window)
                        }
                    } else if (!positionedByMode) {
                        updateOverlayPanelPosition(managedOverlayId)
                    }

                    if (markReady) {
                        this[readyKey] = true
                    }

                    if (desiredMode !== 'sheet') {
                        if (reveal) {
                            revealTeleportedMenuPanel(menu, this[openKey])
                        } else if (this[openKey] && markReady) {
                            menu.classList.remove('is-closing')
                            menu.classList.add('is-open')
                        }
                    }

                    return
                }

                // Overlay entry gone (displaced/closed) but Alpine still flagged managed —
                // fall through to local anchoring so the panel cannot float detached.
                this.__fffOverlayManaged = false
            }

            const overlayMode = (this.resolveMenuPresentation?.() ?? resolveOverlayMode(window))

            this.__fffOverlayMode = overlayMode
            applyOverlayPresentation(menu, overlayMode)
            applyTeleportedMenuTheme(menu, { variant: menuThemeVariant })
            this.syncOpenOverlaySheetEnvironment(overlayMode, menu)

            if (overlayMode === 'sheet') {
                menu.style.position = 'fixed'
                menu.style.zIndex = String(resolveOwnedOverlayStack(menu, trigger, OVERLAY_SHEET_PANEL_Z_INDEX).panel)
                menu.style.setProperty('z-index', String(resolveOwnedOverlayStack(menu, trigger, OVERLAY_SHEET_PANEL_Z_INDEX).panel), 'important')
                menu.style.setProperty('left', '0', 'important')
                menu.style.setProperty('right', '0', 'important')
                menu.style.setProperty('inset-inline', '0', 'important')
                menu.style.setProperty('width', '100%', 'important')
                menu.style.setProperty('min-width', '0', 'important')
                menu.style.setProperty('max-width', 'none', 'important')
                menu.style.removeProperty('top')
                menu.style.marginTop = '0'
                menu.classList.remove('fff-teleported-menu--above', 'fff-teleported-menu--below')
                syncOverlaySheetToVisualViewport(menu, window)

                // Mirror trigger writing direction so sheet search/options use [dir=rtl] CSS.
                const sheetDirection = typeof window.getComputedStyle === 'function'
                    ? window.getComputedStyle(trigger).direction || 'ltr'
                    : 'ltr'
                menu.dir = sheetDirection

                if (markReady) {
                    this[readyKey] = true
                }

                // Never glass-reveal or flip is-open here — playSheetEnter owns enter.
                // Keyboard resize must not refit; async content growth must.

                if (
                    this[openKey]
                    && menu.classList?.contains?.('is-open')
                    && menu.dataset?.fffSheetEntering !== 'true'
                ) {
                    fitOverlaySheetToContent(menu, window)
                    syncOverlaySheetToVisualViewport(menu, window)
                }

                return
            }

            const rect = trigger.getBoundingClientRect()
            const gap = menuGap
            const viewportPadding = 16
            const align = this.resolveDropdownAlign?.() === 'end' ? 'end' : 'start'
            const shouldMatchTriggerWidth = typeof this.resolveMatchTriggerWidth === 'function'
                ? this.resolveMatchTriggerWidth()
                : matchTriggerWidth
            const resolvedMinWidth = typeof this.resolveMenuMinWidth === 'function'
                ? this.resolveMenuMinWidth()
                : minMenuWidth
            const menuWidth = shouldMatchTriggerWidth
                ? Math.min(Math.max(rect.width, resolvedMinWidth), window.innerWidth - (viewportPadding * 2))
                : Math.min(Math.max(resolvedMinWidth, 0), window.innerWidth - (viewportPadding * 2))

            const direction = typeof window.getComputedStyle === 'function'
                ? window.getComputedStyle(trigger).direction || 'ltr'
                : 'ltr'
            const forcedPlacement = this.position === 'top' || this.position === 'bottom'
                ? this.position
                : null

            menu.style.position = 'fixed'
            // Always pin width before measure — content-sized first paint (e.g. phone
            // country list) jumps when overlay later applies minMenuWidth.
            // Prefer setProperty(..., important) so a prior sheet `width: 100% !important`
            // cannot win; also assign `.width` for plain style mocks / older paths.
            const widthPx = `${Math.round(menuWidth)}px`
            menu.style.width = widthPx
            if (typeof menu.style.setProperty === 'function') {
                menu.style.setProperty('width', widthPx, 'important')
                menu.style.setProperty('max-width', 'none', 'important')
                menu.style.setProperty('--fff-select-menu-width', widthPx)
            }
            const stack = resolveOwnedOverlayStack(menu, trigger, 20)
            menu.style.zIndex = stack.nested ? String(stack.panel) : resolveTeleportedMenuZIndex()
            menu.style.top = `${Math.round(rect.bottom + gap)}px`
            menu.style.left = `${Math.round(rect.left)}px`
            menu.style.marginTop = '0'
            // Ensure prior sheet pins cannot stretch the panel to the viewport bottom.
            if (typeof menu.style.removeProperty === 'function') {
                menu.style.removeProperty('bottom')
                menu.style.removeProperty('height')
                menu.style.removeProperty('max-height')
                menu.style.removeProperty('min-height')
            }

            const bounds = resolveOverlayViewport(trigger, window, viewportPadding)
            const anchoredMaxHeight = this.resolveAnchoredMenuMaxHeight?.(rect, bounds, gap)
            if (Number.isFinite(anchoredMaxHeight) && anchoredMaxHeight >= 0) {
                menu.style.setProperty('max-height', `${Math.min(bounds.height, anchoredMaxHeight)}px`, 'important')
            } else if (menu.offsetHeight > bounds.height) menu.style.maxHeight = `${bounds.height}px`
            const panelHeight = menu.offsetHeight || menu.getBoundingClientRect().height
            let left = resolveTeleportedMenuHorizontalLeft({
                triggerRect: rect,
                // Opening scale changes the visual rectangle, not layout width.
                // End alignment must remain correct after that scale reaches 1.
                menuWidth: Math.round(menuWidth),
                align,
                direction,
                viewportPadding,
                windowWidth: window.innerWidth,
            })

            const { top, opensAbove } = resolveTeleportedMenuVerticalPlacement({
                triggerRect: rect,
                panelHeight,
                gap,
                viewportPadding,
                windowHeight: window.innerHeight,
                forcedPlacement,
                viewportTop: bounds.top,
                viewportBottom: bounds.bottom,
            })

            const floating = resolveFloatingPanelPosition(this.resolveFloatingPosition?.(rect), menu, window,
                { width: menuWidth, height: panelHeight })
            menu.style.top = `${floating?.top ?? Math.round(top)}px`
            menu.style.left = `${floating?.left ?? Math.round(left)}px`
            menu.style.right = 'auto'

            menu.dir = direction

            menu.classList.toggle('fff-teleported-menu--above', opensAbove)
            menu.classList.toggle('fff-teleported-menu--below', ! opensAbove)
            this.notifyMenuPositioned()

            if (markReady) {
                this[readyKey] = true
            }

            if (reveal) {
                revealTeleportedMenuPanel(menu, this[openKey])
            } else if (this[openKey] && markReady) {
                menu.classList.remove('is-closing')
                menu.classList.add('is-open')
            }
        },

        applySelectMenuTheme(menu) {
            applyTeleportedMenuTheme(menu, { variant: menuThemeVariant })
        },

        /**
         * Keep body scroll lock + sheet backdrop/dismiss in sync when the open
         * overlay flips panel↔sheet on resize (or when first anchored as sheet).
         *
         * @param {'panel' | 'sheet'} mode
         * @param {HTMLElement | null | undefined} [menu]
         * @param {{ bindDismiss?: boolean }} [options]
         */
    }
}
