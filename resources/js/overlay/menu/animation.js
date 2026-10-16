import { menuPanelMotion } from './panel-motion.js'
import { createResourceScope } from '../../runtime/lifecycle/resource-scope.js'
import { prefersReducedMotion } from '../../core/theme-utils.js'
import { resolveOverlayMode } from '../../core/overlay-mode.js'
import { fitOverlaySheetToContent } from '../../core/overlay-sheet-dismiss.js'
import { OVERLAY_SHEET_RESIZE_TRANSITION } from '../../core/overlay-sheet-sizing.js'
import { menuIsSheet, clearOverlaySheetInlineStyles, removeSheetChrome } from './presentation.js'

/** One animation generation per panel; no queued frame may revive a closed panel. */
const sessions = new WeakMap()
const now = () => typeof performance !== 'undefined' ? performance.now() : Date.now()
const connected = (menu) => menu?.isConnected !== false

function frame(scope, callback) {
    if (scope.disposed) return
    let release = () => {}
    let settled = false
    const finish = () => {
        if (settled) return
        settled = true
        release()
        if (!scope.disposed) callback()
    }
    const id = requestAnimationFrame(finish)
    const deadline = setTimeout(finish, 100)
    release = scope.defer(() => { cancelAnimationFrame(id); clearTimeout(deadline) })
}

function timeout(scope, callback, delay) {
    const id = setTimeout(() => { release(); if (!scope.disposed) callback() }, delay)
    const release = scope.defer(() => clearTimeout(id))
}

export function cancelMenuCloseAnimation(menu) {
    if (!menu) return
    const session = sessions.get(menu)
    sessions.delete(menu)
    session?.effects.dispose()
    for (const waiter of session?.waiters ?? []) waiter.dispose()
    if (menu.dataset) delete menu.dataset.fffSheetEntering
    // Compatibility with panels created before the new module was loaded.
    if (menu.__fffMenuCloseTimeout) clearTimeout(menu.__fffMenuCloseTimeout)
    if (menu.__fffMenuCloseListener) menu.removeEventListener('transitionend', menu.__fffMenuCloseListener)
    menu.__fffMenuCloseTimeout = null
    menu.__fffMenuCloseListener = null
}

function begin(menu, phase) {
    cancelMenuCloseAnimation(menu)
    const session = { phase, effects: createResourceScope(), waiters: new Set() }
    sessions.set(menu, session)
    session.current = () => sessions.get(menu) === session && connected(menu)
    return session
}

export function revealTeleportedMenuPanel(menu, isOpen) {
    if (!menu) return
    const session = begin(menu, 'reveal')
    menu.classList.remove('is-closing')
    if (isOpen && menu.classList.contains('is-open')) { session.phase = 'open'; return }
    menu.classList.remove('is-open')
    if (!isOpen) return
    if (prefersReducedMotion()) { menu.classList.add('is-open'); session.phase = 'open'; return }
    void menu.offsetWidth
    frame(session.effects, () => {
        if (!session.current()) return
        menu.classList.add('is-open')
        session.phase = 'open'
    })
}

export function playSheetEnter(menu) {
    if (!menu) return
    const session = begin(menu, 'enter')
    menu.classList.remove('is-open', 'is-closing')
    menu.style.setProperty('transition', 'none', 'important')
    if (!menu.dataset?.fffSheetFittedHeight) fitOverlaySheetToContent(menu, window)
    menu.dataset.fffSheetEntering = 'true'
    menu.style.setProperty('transform', 'translate3d(0, 100%, 0)', 'important')
    void menu.offsetWidth

    const finish = () => {
        if (!session.current() || session.phase !== 'enter') return
        // Background tabs and a busy Livewire morph can delay both paint frames
        // beyond the deadline. Complete the visual transition before cancelling
        // pending frames, otherwise the sheet stays parked below the viewport.
        if (!menu.classList.contains('is-open')) reveal()
        session.phase = 'open'
        delete menu.dataset.fffSheetEntering
        session.effects.dispose()
        menu.style.setProperty('transition', prefersReducedMotion() ? 'none' : OVERLAY_SHEET_RESIZE_TRANSITION, 'important')
        fitOverlaySheetToContent(menu, window)
    }
    const reveal = () => {
        if (!session.current() || session.phase !== 'enter') return
        menu.style.setProperty('transition', 'transform 0.25s cubic-bezier(0.32, 0.72, 0, 1)', 'important')
        menu.style.removeProperty('transform')
        menu.classList.remove('is-closing')
        menu.classList.add('is-open')
        timeout(session.effects, finish, 280)
    }
    if (prefersReducedMotion()) {
        menu.style.removeProperty('transform')
        menu.style.removeProperty('transition')
        menu.classList.add('is-open')
        finish()
        return
    }
    session.effects.listen(menu, 'transitionend', (event) => {
        if (event.target === menu && event.propertyName === 'transform') finish()
    })
    frame(session.effects, () => frame(session.effects, reveal))
}

/** Work belongs to the current enter generation; close/displace cancels it. */
export function runAfterSheetEnter(menu, callback, { timeoutMs = 320, readyTimeoutMs = 1200, isCurrent = () => true } = {}) {
    if (typeof callback !== 'function') return () => {}
    const sheet = menuIsSheet(menu) || (typeof window !== 'undefined' && resolveOverlayMode(window) === 'sheet')
    if (!sheet || !menu) { callback(); return () => {} }
    if (!connected(menu) || menu.classList.contains('is-closing')) return () => {}
    let generation = sessions.get(menu)
    const waiter = createResourceScope()
    generation?.waiters.add(waiter)
    waiter.defer(() => generation?.waiters.delete(waiter))
    const started = now()
    let enterStarted = generation ? started : null
    const tick = () => {
        if (!isCurrent() || !connected(menu) || menu.classList.contains('is-closing')
            || (generation && sessions.get(menu) !== generation)) { waiter.dispose(); return }
        if (!generation && sessions.has(menu)) {
            generation = sessions.get(menu)
            enterStarted = now()
            generation.waiters.add(waiter)
        }
        const open = menu.classList.contains('is-open')
        const entering = menu.dataset?.fffSheetEntering === 'true'
        if (open && (!entering || (enterStarted !== null && now() - enterStarted >= timeoutMs))) {
            waiter.dispose()
            callback()
            return
        }
        if (now() - started >= readyTimeoutMs + timeoutMs) { waiter.dispose(); return }
        frame(waiter, tick)
    }
    frame(waiter, tick)
    return () => waiter.dispose()
}

function completeExit(menu, session, callback, { sheet = false } = {}) {
    if (!session.current() || session.phase !== 'exit') return
    session.phase = 'closed'
    session.effects.dispose()
    menu.classList.remove('is-closing', 'is-open')
    if (sheet) {
        menu.classList.remove('fff-overlay-sheet', 'fff-teleported-menu--sheet')
        delete menu.dataset.fffOverlaySheet
        delete menu.dataset.fffOverlayPresentation
        clearOverlaySheetInlineStyles(menu)
        removeSheetChrome(menu)
        for (const [property, value] of Object.entries({ visibility: 'hidden', 'max-height': '0', opacity: '0', 'pointer-events': 'none' })) {
            menu.style.setProperty(property, value, 'important')
        }
    }
    callback?.()
}

export function playSheetExit(menu, onDone) {
    if (!menu) { onDone?.(); return }
    const session = begin(menu, 'exit')
    for (const [property, value] of Object.entries({ width: '100%', 'min-width': '0', 'max-width': 'none', left: '0', right: '0', 'inset-inline': '0' })) {
        menu.style.setProperty(property, value, 'important')
    }
    const complete = () => completeExit(menu, session, onDone, { sheet: true })
    if (prefersReducedMotion()) { complete(); return }
    menu.classList.add('is-open')
    menu.classList.remove('is-closing')
    menu.style.setProperty('transition', 'none', 'important')
    menu.style.setProperty('transform', 'translate3d(0, 0, 0)', 'important')
    void menu.offsetWidth
    menu.style.setProperty('transition', 'transform 0.2s cubic-bezier(0.32, 0.72, 0, 1)', 'important')
    menu.style.setProperty('transform', 'translate3d(0, 100%, 0)', 'important')
    menu.classList.remove('is-open')
    menu.classList.add('is-closing')
    session.effects.listen(menu, 'transitionend', (event) => {
        if (event.target === menu && event.propertyName === 'transform') complete()
    })
    timeout(session.effects, complete, 240)
}

export function hideTeleportedMenuPanel(menu, onHidden) {
    if (!menu) { onHidden?.(); return }
    if (menuIsSheet(menu)) { playSheetExit(menu, onHidden); return }
    const session = begin(menu, 'exit')
    const complete = () => completeExit(menu, session, onHidden)
    if (prefersReducedMotion()) { complete(); return }
    const presentation = menuPanelMotion(menu)
    if (presentation?.hide) {
        try {
            menu.classList.add('is-closing')
            const effect = presentation.hide()
            if (effect?.finished) {
                // Sample the visible pose before removing the open class. The
                // owned effect now controls every painted exit frame.
                menu.classList.remove('is-open')
                session.effects.defer(() => effect.cancel?.())
                // Cancellation of an obsolete generation must not close a reopened
                // panel; a failed current effect must still release its owner.
                Promise.resolve(effect.finished).then(complete, complete)
                return
            }
        } catch { /* A presentation failure falls back to the shared exit. */ }
    }
    menu.classList.remove('is-open')
    menu.classList.add('is-closing')
    session.effects.listen(menu, 'transitionend', (event) => {
        if (event.target === menu && ['opacity', 'transform'].includes(event.propertyName)) complete()
    })
    timeout(session.effects, complete, 220)
}
