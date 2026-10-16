import { createMenuHighlight } from './highlight.js'
import { measureVideoMenu } from './measure.js'
import { createSettingsMotion, SETTINGS_MENU_POPOVER_TRANSITION_MS } from './motion.js'
import { getAvailableMenuHeight } from '../ui/position.js'

/** One popup and natural-height views; positioning stays relative to the player. */
export function createVideoSettings(component, runtime) {
    const menu = component.$refs.settingsMenu, trigger = component.$refs.settingsTrigger, frame = component.$refs.frame
    if (!menu || !trigger || !frame) return null
    const window = menu.ownerDocument.defaultView, motion = createSettingsMotion(runtime.scope, window)
    const highlight = createMenuHighlight(menu, runtime.scope, window)
    const panels = { root: component.$refs.settingsPanelRoot, speed: component.$refs.settingsPanelSpeed, quality: component.$refs.settingsPanelQuality }
    let desired = false, popupGeneration = 0, viewGeneration = 0, view = 'root', observerRelease = null
    const visibleItems = panel => [...(panel?.querySelectorAll('button:not(:disabled)') ?? [])]
    const focus = returnTo => {
        const items = visibleItems(panels[view])
        const origin = view === 'root' && returnTo ? items.find(e => e.getAttribute('data-menu-target') === returnTo) : null
        const item = origin ?? items.find(e => e.getAttribute('aria-checked') === 'true') ?? items[0]
        item?.focus({ preventScroll: true })
        // Keep the focused row visible in short players without scrolling the application page.
        const row = item?.getBoundingClientRect?.(), panel = panels[view], bounds = panel?.getBoundingClientRect()
        if (row && bounds) {
            if (row.bottom > bounds.bottom - 4) panel.scrollTop += row.bottom - bounds.bottom + 4
            else if (row.top < bounds.top + 4) panel.scrollTop -= bounds.top + 4 - row.top
        }
    }
    function dimensions(panel) {
        const bounds = frame.getBoundingClientRect(), anchor = trigger.getBoundingClientRect()
        const availableWidth = Math.max(1, bounds.width - 24), availableHeight = Math.max(1, getAvailableMenuHeight(anchor, bounds, { sideOffset: 12, boundaryOffset: 12 }))
        menu.style.minWidth = `${Math.min(176, availableWidth)}px`
        menu.style.maxWidth = `${availableWidth}px`
        menu.style.maxHeight = `${Math.min(224, availableHeight)}px`
        const natural = measureVideoMenu(panel, { root: panel === panels.root, window, availableWidth })
        // Animate the content width; CSS owns the outer minimum, as it owns the height cap.
        // Pre-clamping the target changes the curve where the popup reaches either limit.
        return { width: Math.min(availableWidth, Math.max(Math.min(168, availableWidth), natural.width)), height: natural.height, maxHeight: availableHeight }
    }
    function position() {
        const bounds = frame.getBoundingClientRect(), anchor = trigger.getBoundingClientRect(), parent = menu.parentElement.getBoundingClientRect()
        const center = anchor.left + anchor.width / 2
        menu.style.left = `${center - parent.left}px`
        menu.style.translate = `clamp(${bounds.left + 12 - center}px, -50%, calc(${bounds.right - 12 - center}px - 100%)) 0px`
        menu.style.bottom = `${parent.bottom - anchor.top + 12}px`
    }
    function size(panel, animate = true) {
        const current = window.getComputedStyle(menu)
        const before = { width: current.width, height: current.height }
        const target = dimensions(panel)
        position()
        menu.style.setProperty('--fff-video-field-menu-max-height', `${target.maxHeight}px`)
        const styles = { width: `${target.width}px`, height: `${Math.max(1, target.height)}px` }
        // Separate size owner from popup opacity/scale, so opening and resizing cannot cancel each other.
        runtime.menuSizeMotion ??= createSettingsMotion(runtime.scope, window)
        if (animate) runtime.menuSizeMotion.animate(menu, styles, { from: before })
        else { runtime.menuSizeMotion.cancel(menu); Object.assign(menu.style, styles) }
    }
    function observe() {
        if (observerRelease || !window.ResizeObserver) return
        const observer = new window.ResizeObserver(() => runtime.schedule('settings-resize', () => { if (desired) size(panels[view], false) }))
        observer.observe(frame)
        observerRelease = runtime.scope.trackObserver(observer)
    }
    function reset() {
        highlight.reset(); viewGeneration++; view = 'root'; component.settingsView = 'root'
        menu.removeAttribute('data-view-transition')
        for (const [key, panel] of Object.entries(panels)) {
            if (!panel) continue
            motion.cancel(panel); panel.hidden = key !== 'root'; panel.inert = key !== 'root'
            panel.style.translate = ''; panel.style.filter = ''; panel.removeAttribute('data-child-open'); panel.removeAttribute('data-visited')
        }
    }
    function open() {
        if (runtime.disposed || !component.canInteract) return
        desired = true; component.settingsOpen = true
        const token = ++popupGeneration, wasHidden = menu.hidden
        menu.hidden = false
        if (wasHidden) { reset(); size(panels.root, false) }
        observe()
        const target = { opacity: '1', filter: 'blur(0px)', scale: '1', transform: 'translateY(0px)' }
        motion.animate(menu, target, { duration: SETTINGS_MENU_POPOVER_TRANSITION_MS, from: wasHidden ? { opacity: '0', filter: 'blur(4px)', scale: '.95', transform: 'translateY(8px)' } : null }).then(done => { if (done && token === popupGeneration && desired) focus() })
        component.revealUi()
    }
    function close({ returnFocus = false } = {}) {
        if (!desired) return
        desired = false; const token = ++popupGeneration
        observerRelease?.(); observerRelease = null; runtime.cancel('settings-resize')
        motion.animate(menu, { opacity: '0', filter: 'blur(4px)', scale: '.95', transform: 'translateY(0px)' }, { duration: SETTINGS_MENU_POPOVER_TRANSITION_MS }).then(done => {
            if (!done || token !== popupGeneration || desired || runtime.disposed) return
            menu.hidden = true; component.settingsOpen = false; reset()
            if (returnFocus) trigger.focus({ preventScroll: true })
            component.revealUi()
        })
    }
    async function navigate(next) {
        if (!desired || !panels[next] || next === view || runtime.disposed) return
        highlight.reset()
        menu.toggleAttribute('data-view-transition', true)
        const previous = view, oldPanel = panels[previous], newPanel = panels[next], wasHidden = newPanel.hidden, token = ++viewGeneration
        const rtl = window.getComputedStyle(menu).direction === 'rtl' ? -1 : 1
        view = next; component.settingsView = next
        newPanel.hidden = false; newPanel.inert = false; oldPanel.inert = true
        panels.root.toggleAttribute('data-child-open', next !== 'root')
        size(newPanel)
        const newFrom = { translate: `${next === 'root' ? -100 * rtl : 100 * rtl}% 0px`, filter: 'blur(8px)' }
        const entering = motion.animate(newPanel, { translate: '0% 0px', filter: 'blur(0px)' }, { from: wasHidden ? newFrom : null })
        const exiting = motion.animate(oldPanel, { translate: `${next === 'root' ? 100 * rtl : -100 * rtl}% 0px`, filter: 'blur(8px)' })
        await Promise.all([entering, exiting])
        if (token !== viewGeneration || !desired || runtime.disposed) return
        for (const [key, panel] of Object.entries(panels)) if (panel && key !== next) panel.hidden = true
        menu.removeAttribute('data-view-transition')
        focus(previous)
    }
    runtime.scope.listen(menu, 'keydown', event => {
        const rtl = window.getComputedStyle(menu).direction === 'rtl'
        if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); close({ returnFocus: true }); return }
        if ((event.key === (rtl ? 'ArrowRight' : 'ArrowLeft')) && view !== 'root') { event.preventDefault(); navigate('root'); return }
        if (event.key === 'Tab') { close(); return }
        const items = visibleItems(panels[view]), current = items.indexOf(menu.ownerDocument.activeElement)
        let index = null
        if (event.key === 'ArrowDown') index = (current + 1) % items.length
        if (event.key === 'ArrowUp') index = (current - 1 + items.length) % items.length
        if (event.key === 'Home') index = 0
        if (event.key === 'End') index = items.length - 1
        if (index !== null) { event.preventDefault(); event.stopPropagation(); items[index]?.focus({ preventScroll: true }) }
        if (event.key === (rtl ? 'ArrowLeft' : 'ArrowRight') && view === 'root') { event.preventDefault(); items[current]?.click() }
    })
    runtime.scope.defer(() => { desired = false; popupGeneration++; viewGeneration++; observerRelease?.(); motion.dispose(); runtime.menuSizeMotion?.dispose(); menu.hidden = true })
    return { open, close, navigate, toggle: () => desired ? close() : open(), get desired() { return desired } }
}
