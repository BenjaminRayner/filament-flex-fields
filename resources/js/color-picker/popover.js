import { colorRuntime, cancelColorDrag } from './lifetime.js'
import { openExclusiveFlexDropdown, resolveDropdownOwnerId } from '../core/flex-dropdown-coordinator.js'
import { resolveOverlayViewport, resolveOverlayBaseLayer } from '../overlay/geometry/viewport.js'
import { resolveOwnedOverlayStack } from '../overlay/ownership/stacking.js'
import { resolveTeleportedMenuHorizontalLeft, resolveTeleportedMenuVerticalPlacement } from '../core/teleported-menu-position.js'
export function createColorPopover() {
    return {
        panelOpen: false, panelStyle: { position: 'fixed', visibility: 'hidden' },
        togglePanel() { if (this.readOnly || this.inline) return; this.panelOpen ? this.closePanel() : this.openPanel() },
        openPanel() {
            if (this.readOnly) return
            if (this.panelOpen) return
            const owner = resolveDropdownOwnerId(this, 'fff-color')
            if (owner) openExclusiveFlexDropdown(owner)
            this.panelOpen = true
            this.$nextTick(() => {
                if (!this.panelOpen || colorRuntime(this).disposed) return
                this.positionPanel()
                const r = colorRuntime(this)
                let frame = null
                const update = () => {
                    if (frame != null) return
                    frame = requestAnimationFrame(() => { frame = null; this.positionPanel() })
                }
                window.addEventListener('resize', update); window.addEventListener('scroll', update, true)
                r.cleanup.push(() => { window.removeEventListener('resize', update); window.removeEventListener('scroll', update, true); if (frame != null) cancelAnimationFrame(frame) })
                if (typeof ResizeObserver === 'function') { const observer = new ResizeObserver(update); observer.observe(this.$refs.panel); observer.observe(this.$refs.trigger); r.cleanup.push(() => observer.disconnect()) }
                this.$refs.panel.querySelector('input,button,[tabindex="0"]')?.focus({ preventScroll: true })
            })
        },
        positionPanel() {
            if (!this.panelOpen || !this.$refs.trigger || !this.$refs.panel) return
            const bounds = resolveOverlayViewport(this.$refs.trigger, window, 8)
            this.$refs.panel.style.maxHeight = `${bounds.height}px`
            const anchor = this.$refs.trigger.getBoundingClientRect(), panel = { height: this.$refs.panel.offsetHeight }, width = Math.min(this.panelWidth, window.innerWidth - 16)
            const placement = { triggerRect: anchor, panelHeight: panel.height, gap: 8, viewportPadding: 8, windowHeight: window.innerHeight, viewportTop: bounds.top, viewportBottom: bounds.bottom }
            const { opensAbove: above } = resolveTeleportedMenuVerticalPlacement({ ...placement, forcedPlacement: this.placement.startsWith('top') ? 'top' : null })
            const maxHeight = Math.max(0, above ? anchor.top - 8 - bounds.top : bounds.bottom - anchor.bottom - 8)
            const { top: y } = resolveTeleportedMenuVerticalPlacement({ ...placement, panelHeight: Math.min(panel.height, maxHeight), forcedPlacement: above ? 'top' : 'bottom' })
            const stack = resolveOwnedOverlayStack(this.$refs.panel, this.$refs.trigger, resolveOverlayBaseLayer(this.$refs.trigger))
            const style = getComputedStyle(this.__colorOwner)
            const x = resolveTeleportedMenuHorizontalLeft({ triggerRect: anchor, menuWidth: width, align: this.placement.endsWith('end') ? 'end' : 'start', direction: style.direction, viewportPadding: 8, windowWidth: window.innerWidth })
            this.$refs.panel.dataset.fffDropdownOwner = resolveDropdownOwnerId(this, 'fff-color')
            this.$refs.panel.dataset.theme = this.__colorOwner.closest('.dark') ? 'dark' : 'light'
            this.panelStyle = { position: 'fixed', visibility: 'visible', zIndex: String(stack.panel), maxHeight: `${maxHeight}px`, left: `${x}px`, top: `${y}px`, width: `${width}px`, transformOrigin: `${this.placement.endsWith('end') !== (style.direction === 'rtl') ? 'right' : 'left'} ${above ? 'bottom' : 'top'}`, '--fff-global-radius': style.getPropertyValue('--fff-global-radius') || '.75rem', '--fff-color-panel-radius': `${this.panelRadius}px`, '--fff-color-duration': `${this.animationDuration}ms`, '--fff-color-exit-duration': `${Math.round(this.animationDuration * 2 / 3)}ms`, '--fff-color-enter-offset': above ? '4px' : '-4px' }
        },
        closePanel(restoreFocus = false) {
            const r = colorRuntime(this)
            if (r.drag) { this.hsva = { ...r.drag.before }; this.syncInputs() }
            cancelColorDrag(this); r.epoch++; r.dropper?.abort(); r.dropper = null
            r.cleanup.splice(0).forEach(cleanup => cleanup())
            this.panelOpen = false
            this.$refs.panel?.dispatchEvent(new CustomEvent('fff-parent-overlay-close', { bubbles: true }))
            if (restoreFocus) this.$refs.trigger?.focus({ preventScroll: true })
        },
        onPanelKey(event) {
            if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); this.closePanel(true) }
            if (event.key !== 'Tab' || this.inline) return
            const controls = [...this.$refs.panel.querySelectorAll('button:not(:disabled),input:not(:disabled),select:not(:disabled),[tabindex="0"]')].filter(el => el.getClientRects().length)
            const first = controls[0], last = controls.at(-1)
            if (event.shiftKey && document.activeElement === first || !event.shiftKey && document.activeElement === last) { event.preventDefault(); this.closePanel(true) }
        },
        onOutsidePointer(event) {
            const nestedOwner = event.target?.closest?.('[data-fff-dropdown-parent]')?.dataset.fffDropdownParent
            if (nestedOwner === resolveDropdownOwnerId(this, 'fff-color')) return
            if (this.panelOpen && !this.__colorOwner.contains(event.target) && !this.$refs.panel?.contains(event.target)) this.closePanel()
        },
    }
}
