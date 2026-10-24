import { wireExclusiveFlexDropdown } from '../../core/flex-dropdown-coordinator.js'
import { resolveOverlayMode } from '../../core/overlay-mode.js'
import { removeOverlayBackdrop } from '../../core/overlay-backdrop.js'
import { claimOverlayExclusive, closeOverlayPanel, emitOverlayOpenLatency, openOverlayPanel, releaseOverlayExclusive } from '../../core/overlay-runtime-bridge.js'
import { cancelMenuCloseAnimation, playSheetEnter, playSheetExit, hideTeleportedMenuPanel } from './animation.js'
import { menuIsSheet, applyOverlayPresentation, clearOverlaySheetInlineStyles, removeSheetChrome } from './presentation.js'
import { applyTeleportedMenuTheme } from './theme.js'
import { releaseOverlaySheetEnvironment } from './sheet-environment.js'
import { observeOwnerConnectivity } from '../../runtime/lifecycle/owner-connectivity.js'
import { mountOwnedMenuPortal } from './portal-owner.js'
import { menuPanelMotion } from './panel-motion.js'
export function createMenuLifecycleMixin({ openKey, readyKey, ownerIdPrefix, minMenuWidth, matchTriggerWidth, menuGap, menuThemeVariant, onMenuClose }) {
    return {
        closeTeleportedMenu() {
            if (! this[openKey] || this.__fffSheetClosing || this.__fffPanelClosing) {
                return
            }

            const menu = this.resolveMenuElement()
            const sheet = menuIsSheet(menu) || this.__fffOverlayMode === 'sheet'

            if (sheet) {
                this.__fffSheetClosing = true

                if (menu && ! menuIsSheet(menu)) {
                    applyOverlayPresentation(menu, 'sheet')
                }

                removeOverlayBackdrop(document, this.resolveMenuOverlayId())

                playSheetExit(menu, () => {
                    this.__fffSheetClosing = false
                    this[openKey] = false
                })

                return
            }

            this.__fffPanelClosing = true
            this._surfaceOpenSession?.dispose()
            hideTeleportedMenuPanel(menu, () => {
                this.__fffPanelClosing = false
                this[openKey] = false
            })
        },

        /**
         * Exclusive displace must flip open state immediately. Delaying for the
         * glass exit animation leaves comboboxOpen=true and can keep another
         * field's panel painted while a sibling select is opening.
         */
        closeTeleportedMenuImmediate(reason = 'immediate') {
            if (! this[openKey]) {
                return
            }

            const menu = this.resolveMenuElement()

            cancelMenuCloseAnimation(menu)

            if (menu) {
                menu.classList.remove(
                    'is-open',
                    'is-closing',
                    'fff-teleported-menu--sheet',
                    'fff-teleported-menu--panel',
                )
                clearOverlaySheetInlineStyles(menu)
                removeSheetChrome(menu)
            }

            this._pickerCloseReason = reason
            this[openKey] = false
        },

        bindSelectMenuLifecycle({ wireExclusive = true } = {}) {
            if (this.__fffPickerDisposed || this.__fffPickerLifecycleBound) return
            this.__fffPickerLifecycleBound = true
            this._pickerHostElement ??= this.$el
            const scope = this.ensurePickerResourceScope()
            try {
                scope.defer(observeOwnerConnectivity(this._pickerHostElement, () => {
                    const menu = this.resolveMenuElement()
                    this.destroyTeleportedMenuLifecycle()
                    // A skipped framework destroy must not leave its owned portal
                    // under body. Ordinary close keeps markup for the next open.
                    if (menu?.isConnected) menu.remove()
                }))
                const displaceThisMenu = () => {
                    if (! this[openKey]) {
                        return
                    }

                    // Exclusive displace must flip open state immediately so a sibling
                    // picker can claim the overlay. Backdrop / handle dismiss use the
                    // animated sheet exit instead.
                    if (typeof this.closeTeleportedMenuImmediate === 'function') {
                        this.closeTeleportedMenuImmediate('displaced')

                        return
                    }

                    this._pickerCloseReason = 'displaced'
                    this[openKey] = false
                }

                if (wireExclusive) {
                    // Retire only displaced owners, including their painted panel;
                    // the exclusivity registry preserves the ancestor owner chain.
                    wireExclusiveFlexDropdown(this, {
                        openKey,
                        closeMethod: 'closeTeleportedMenuImmediate',
                        ownerIdPrefix,
                    })
                }

                const stopWatching = this.$watch(openKey, (open) => {
                    if (this.__fffPickerDisposed) return
                    const overlayExclusiveId = this.resolveMenuOverlayId()
                    this._pickerHostElement?.setAttribute('data-fff-dropdown-state', open ? 'open' : 'closed')
                    if (!open) this._pickerHostElement?.setAttribute('data-fff-dropdown-close-reason', this._pickerCloseReason ?? 'state')
                    else this._pickerCloseReason = null

                    if (open) {
                        // The visual anchor may be an outer input wrapper. Portal
                        // ownership follows the controller's preserved DOM instead.
                        mountOwnedMenuPortal(this.resolveMenuPortalAnchor?.() ?? this._pickerHostElement, this.resolveMenuElement())
                        this.__fffMenuOpenStartedAt = typeof performance !== 'undefined' ? performance.now() : Date.now()

                        // Lock immediately so exclusive displace works before the
                        // teleported panel is measured/anchored; upgrade to open().
                        if (wireExclusive) claimOverlayExclusive(overlayExclusiveId, {
                            onDisplace: displaceThisMenu,
                        })
                        this.scheduleMenuPosition({
                            onAnchored: () => {
                                const trigger = this.resolveMenuTriggerRef()
                                const menu = this.resolveMenuElement()
                                const anchoredOverlayId = this.resolveMenuOverlayId()

                                if (! trigger || ! menu) {
                                    this.bindMenuListeners()

                                    return
                                }

                                // A menu mounted by the first open intent becomes
                                // available after Alpine's DOM flush. Claim its
                                // actual focus boundary before revealing it.
                                mountOwnedMenuPortal(this.resolveMenuPortalAnchor?.() ?? this._pickerHostElement, menu)

                                const overlayMode = (this.resolveMenuPresentation?.() ?? resolveOverlayMode(window))

                                applyOverlayPresentation(menu, overlayMode)

                                openOverlayPanel({
                                    id: anchoredOverlayId,
                                    panel: menu,
                                    anchor: trigger,
                                    mode: overlayMode,
                                    exclusive: wireExclusive,
                                    manageVisibility: false,
                                    onDisplace: displaceThisMenu,
                                    onAnchorUnavailable: this.dismissWhenAnchorHidden ? () => this.closeTeleportedMenu() : null,
                                    onPositioned: () => this.notifyMenuPositioned?.(),
                                    resolvePositionPresentation: () => menuPanelMotion(menu)?.positionPresentation?.() ?? null,
                                    minWidth: typeof this.resolveMenuMinWidth === 'function'
                                        ? this.resolveMenuMinWidth()
                                        : minMenuWidth,
                                    matchTriggerWidth: typeof this.resolveMatchTriggerWidth === 'function'
                                        ? this.resolveMatchTriggerWidth()
                                        : matchTriggerWidth,
                                    align: this.resolveDropdownAlign?.() === 'end' ? 'end' : 'start',
                                    gap: menuGap,
                                    resolveAnchoredMenuMaxHeight: typeof this.resolveAnchoredMenuMaxHeight === 'function'
                                        ? (rect, bounds, gap) => this.resolveAnchoredMenuMaxHeight(rect, bounds, gap) : null,
                                    resolveFloatingPosition: typeof this.resolveFloatingPosition === 'function'
                                        ? rect => this.resolveFloatingPosition(rect) : null,
                                    position: this.position === 'top' || this.position === 'bottom'
                                        ? this.position
                                        : null,
                                })
                                applyTeleportedMenuTheme(menu, { variant: menuThemeVariant })
                                this.__fffOverlayManaged = true
                                this.__fffOverlayMode = overlayMode
                                // Lock scroll + backdrop before enter; bind dismiss after so
                                // snap/layout work cannot delay the first slide frame.
                                this.syncOpenOverlaySheetEnvironment(overlayMode, menu, { bindDismiss: false })

                                if (typeof this.afterOverlayPanelOpened === 'function') {
                                    this.afterOverlayPanelOpened(overlayMode, menu)
                                }

                                if (overlayMode === 'sheet') {
                                    playSheetEnter(menu)
                                    this.syncOpenOverlaySheetEnvironment('sheet', menu)
                                }
                                this.onMenuSurfaceAnchored?.()
                                // readyKey is set by scheduleMenuPosition after onAnchored returns
                                // so first paint already includes overlay geometry.

                                if (typeof this.__fffMenuOpenStartedAt === 'number') {
                                    const latency = (typeof performance !== 'undefined' ? performance.now() : Date.now())
                                        - this.__fffMenuOpenStartedAt

                                    emitOverlayOpenLatency(anchoredOverlayId, latency)
                                }

                                // Managed overlays skip scroll listeners (runtime owns those),
                                // but still need resize for panel↔sheet breakpoint flips.
                                this.bindMenuListeners()
                            },
                        })

                        return
                    }

                    this._surfaceOpenSession?.dispose()
                    this._surfaceOpenSession = null
                    const menu = this.resolveMenuElement()

                    if (menu) {
                        cancelMenuCloseAnimation(menu)
                        menu.classList.remove('is-open', 'is-closing', 'fff-teleported-menu--sheet', 'fff-teleported-menu--panel')
                        delete menu.dataset.fffOverlayPresentation
                        delete menu.dataset.fffSheetFittedHeight
                        delete menu.dataset.fffOverlaySnap
                        menu.style.transition = ''
                        menu.style.transform = ''
                        clearOverlaySheetInlineStyles(menu)
                        removeSheetChrome(menu)
                    }

                    this.__fffSheetClosing = false
                    this.__fffPanelClosing = false
                    removeOverlayBackdrop(document, overlayExclusiveId)
                    releaseOverlaySheetEnvironment(this)

                    if (this.__fffOverlayManaged) {
                        closeOverlayPanel(overlayExclusiveId)
                        this.__fffOverlayManaged = false
                    } else {
                        // Still lock-only (anchoring never completed).
                        releaseOverlayExclusive(overlayExclusiveId)
                    }

                    this.__fffOverlayMode = null
                    this.__fffMenuOpenStartedAt = null

                    // Closed geometry is stale after scrolling, resizing or new rows.
                    // Every subsequent open must anchor before its first visible frame.
                    this[readyKey] = false
                    menu?.classList.remove('is-positioned')

                    if (typeof onMenuClose === 'function') {
                        onMenuClose.call(this)
                    }

                    if (typeof this.resetVirtualListScroll === 'function') {
                        this.resetVirtualListScroll()
                    }

                    this.unbindMenuListeners()
                })
                if (typeof stopWatching === 'function') scope.defer(stopWatching)
            } catch (error) {
                this.destroyTeleportedMenuLifecycle()
                throw error
            }
        },

        /**
         * Tear down listeners/overlay/exclusive lock when Alpine destroys the host.
         * A document-owned connection lease also handles skipped Alpine destroy.
         */
        destroyTeleportedMenuLifecycle() {
            this._surfaceOpenSession?.dispose()
            this._surfaceOpenSession = null
            this.disposePickerResources()
            const overlayId = typeof this.resolveMenuOverlayId === 'function'
                ? this.resolveMenuOverlayId()
                : null

            if (this[openKey] && typeof this.closeTeleportedMenuImmediate === 'function') {
                this.closeTeleportedMenuImmediate()
            } else {
                this[openKey] = false
            }

            const menu = typeof this.resolveMenuElement === 'function'
                ? this.resolveMenuElement()
                : null

            if (menu) {
                cancelMenuCloseAnimation(menu)
                menu.classList.remove(
                    'is-open',
                    'is-closing',
                    'fff-teleported-menu--sheet',
                    'fff-teleported-menu--panel',
                )
                clearOverlaySheetInlineStyles(menu)
                removeSheetChrome(menu)
            }

            if (overlayId) {
                removeOverlayBackdrop(document, overlayId)
            }

            releaseOverlaySheetEnvironment(this)

            if (overlayId) {
                if (this.__fffOverlayManaged) {
                    closeOverlayPanel(overlayId)
                    this.__fffOverlayManaged = false
                } else {
                    releaseOverlayExclusive(overlayId)
                }
            }

            this.__fffSheetClosing = false
            this.__fffPanelClosing = false
            this.__fffOverlayMode = null
            this.unbindMenuListeners()

            if (typeof this.__fffDropdownUnbind === 'function') {
                this.__fffDropdownUnbind()
                this.__fffDropdownUnbind = null
            }
        },
    }
}
