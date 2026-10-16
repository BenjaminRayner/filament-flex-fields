import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { waitForAssetActivation } from '../runtime/lifecycle/asset-activation.js'
import { isAssetElementActive } from '../runtime/lifecycle/asset-activation.js'
import { createDeadline } from './deadline.js'
import { createHold, holdClip } from './hold.js'
import { configureConfirmation } from './configuration.js'
import { createHoldFeedback } from './feedback.js'
import { createCountdownPresentation } from './countdown.js'

export function createConfirmation(options = {}) {
    const scope = createResourceScope()
    let ready = null, host, doc, win, pill, motion, hold, pressed = null, phase = 'idle', hovered = false
    let opened = 0, generation = 0, attempt = 'confirm', active = true, countdownView = null, holdFeedback = null
    let suppressedKey = null, ignoreClickUntil = 0
    let releaseGesture = null, fillAnimation = null, cancelDigit = null
    const settings = configureConfirmation(options)
    const clock = () => win?.performance?.now() ?? performance.now()
    const deadline = createDeadline(scope, clock), countdown = createDeadline(scope, clock)
    const live = () => !scope.disposed && active && host?.isConnected !== false && !settings.disabled
    const announce = text => { const element = host?.querySelector('.fff-confirmation__announcement'); if (element) element.textContent = text ?? '' }
    const releasePress = () => { releaseGesture?.(); releaseGesture = null; pressed = null }
    const stopCountdown = () => { countdown.clear(); cancelDigit?.(); cancelDigit = null; countdownView?.stop() }
    const cancelHold = immediate => {
        const wasHolding = hold?.active
        hold?.cancel(immediate); releasePress()
        if (wasHolding && phase === 'confirming' && live()) { deadline.start(settings.confirmTimeout, () => cancel()); syncTimers() }
    }
    const syncTimers = () => { deadline.pause(hovered || doc?.hidden || !active); countdown.pause(Boolean(doc?.hidden || !active)); countdownView?.pause(Boolean(doc?.hidden || !active)) }
    const focus = role => host?.querySelector(`[data-confirm-role="${role}"]`)?.focus({ preventScroll: true })
    const transition = next => {
        if (scope.disposed) return
        const old = phase, retainFocus = host.contains(doc.activeElement) || doc.activeElement === doc.body
        phase = next; host.dataset.state = next
        if (next === 'pending') {
            const label = host.querySelector('[data-pending-label]'); if (label) label.textContent = attempt === 'undo' ? settings.labels.undoing : settings.labels.pending
        }
        deadline.clear(); stopCountdown()
        const busy = next === 'pending'
        host.setAttribute('aria-busy', busy ? 'true' : 'false')
        if (settings.variant === 'morph') {
            motion?.show(next)
            if (next === 'confirming') {
                opened = clock(); announce(settings.prompt); if (retainFocus) focus('confirm')
                deadline.start(settings.confirmTimeout, () => cancel())
            } else if (next === 'done' || next === 'error') {
                announce(next === 'error' ? settings.labels.error : settings.labels.done)
                if (retainFocus) {
                    if (next === 'error') focus('retry')
                    else if (settings.hasUndo) focus('undo')
                    else pill?.focus({ preventScroll: true })
                }
                deadline.start(settings.resultTimeout, () => cancel())
            } else if (next === 'pending' || next === 'countdown') {
                announce(next === 'countdown' ? `${settings.labels.countdown} ${settings.countdown}` : (attempt === 'undo' ? settings.labels.undoing : settings.labels.pending))
                if (retainFocus) pill?.focus({ preventScroll: true })
            } else if (next === 'idle' && old !== 'idle' && retainFocus) focus('open')
        } else {
            holdFeedback?.show(next)
            if (busy) announce(settings.labels.pending)
            else if (next === 'error' || next === 'done') {
                announce(next === 'error' ? settings.labels.error : settings.labels.done)
                deadline.start(settings.resultTimeout, () => cancel())
            }
        }
        host.dispatchEvent(new win.CustomEvent('fff-confirm-state', { bubbles: true, detail: { state: next, previous: old } }))
        syncTimers()
    }
    const cancel = () => {
        if (phase === 'pending' || scope.disposed) return
        cancelHold(true); deadline.clear(); stopCountdown()
        transition('idle'); announce(settings.labels.cancelled)
        host.dispatchEvent(new win.CustomEvent('fff-confirm-cancel', { bubbles: true }))
    }
    const run = async operation => {
        if (!live() || phase === 'pending') return
        cancelHold(true); deadline.clear(); stopCountdown(); attempt = operation
        const token = ++generation
        transition('pending')
        try {
            const result = await settings.execute?.(operation)
            if (scope.disposed || token !== generation || host.isConnected === false) return
            if (result?.fffConfirmation !== true || result.action !== settings.actionName || result.operation !== operation) {
                // A native form/modal has mounted but its action has not executed yet.
                if (api.$wire?.mountedActions?.length) { transition('idle'); return }
                throw new Error('No completed action receipt')
            }
            if (result.success !== true) throw new Error('The action reported failure')
            transition(operation === 'undo' ? 'idle' : 'done')
            if (operation === 'undo') announce(settings.labels.undone)
            host.dispatchEvent(new win.CustomEvent('fff-confirm-result', { bubbles: true, detail: { operation, success: true } }))
        } catch {
            if (!scope.disposed && token === generation) {
                transition('error')
                host.dispatchEvent(new win.CustomEvent('fff-confirm-result', { bubbles: true, detail: { operation, success: false } }))
            }
        }
    }
    const completeHold = () => {
        ignoreClickUntil = clock() + 300
        releasePress()
        host.dispatchEvent(new win.CustomEvent('fff-hold-complete', { bubbles: true }))
        win.navigator?.vibrate?.(12)
        void run('confirm')
    }
    const startHold = (event, target, kind) => {
        if (!live() || hold.active || target.disabled || !['idle', 'confirming'].includes(phase)) return
        if (settings.variant === 'morph' && (phase !== 'confirming' || clock() - opened < 280)) return
        if (kind === 'pointer' && (event.button !== 0 || event.isPrimary === false)) return
        event.preventDefault(); event.stopPropagation()
        const gesture = createResourceScope({ signal: scope.signal }), bounds = target.getBoundingClientRect()
        pressed = { kind, id: kind === 'pointer' ? event.pointerId : event.key }
        if (kind === 'keyboard') suppressedKey = event.key
        releaseGesture = () => gesture.dispose()
        if (kind === 'pointer') {
            const id = event.pointerId
            try { target.setPointerCapture(id) } catch {}
            gesture.defer(() => { if (target.hasPointerCapture?.(id)) target.releasePointerCapture(id) })
            gesture.listen(win, 'pointerup', e => { if (e.pointerId === id) cancelHold(false) })
            gesture.listen(win, 'pointercancel', e => { if (e.pointerId === id) cancelHold(true) })
            gesture.listen(target, 'lostpointercapture', () => cancelHold(false))
            gesture.listen(win, 'pointermove', e => {
                if (e.pointerId === id && (e.clientX < bounds.left || e.clientX > bounds.right || e.clientY < bounds.top || e.clientY > bounds.bottom)) cancelHold(false)
            }, { passive: true })
            gesture.listen(win, 'scroll', () => cancelHold(true), { passive: true, capture: true })
            gesture.listen(win, 'resize', () => cancelHold(true))
        }
        gesture.listen(win, 'blur', () => cancelHold(true))
        gesture.listen(doc, 'visibilitychange', () => { if (doc.hidden) cancelHold(true) })
        deadline.clear(); hold.start()
    }
    const paint = (progress, holding, release, from) => {
        const target = settings.variant === 'morph' ? host?.querySelector('[data-confirm-role="confirm"]') : host?.querySelector('[data-confirm-role="hold"]')
        const overlay = settings.variant === 'morph' ? target?.querySelector('[data-hold-overlay]') : target?.querySelector('.fff-hold-confirm-action__overlay')
        if (!target || !overlay) return
        fillAnimation?.cancel(); fillAnimation = null
        const clip = holdClip(progress, settings.sweep)
        overlay.style.clipPath = clip
        target.dataset.holding = holding ? 'true' : 'false'
        target.style.setProperty('--fff-hold-progress', String(progress))
        if (release && overlay.animate) fillAnimation = overlay.animate([{ clipPath: from }, { clipPath: clip }],
            { duration: settings.releaseDuration, easing: 'cubic-bezier(.22,1,.36,1)' })
    }
    const updateDigits = () => {
        cancelDigit = null
        if (phase !== 'countdown' || scope.disposed) return
        const remaining = countdown.remaining(), number = Math.max(1, Math.ceil(remaining / 1000))
        countdownView?.seconds(number)
        cancelDigit = scope.timeout(updateDigits, Math.max(16, Math.ceil(remaining - (number - 1) * 1000)))
    }
    const startCountdown = () => {
        transition('countdown')
        countdown.start(settings.countdown * 1000, () => void run('confirm'))
        countdownView?.start(settings.countdown)
        updateDigits(); syncTimers()
    }
    const click = event => {
        const button = event.target.closest?.('[data-confirm-role]')
        if (!button || !host.contains(button)) return
        event.preventDefault(); event.stopPropagation()
        if (!live() || button.disabled || phase === 'pending' || clock() < ignoreClickUntil) return
        const role = button.dataset.confirmRole
        if (role === 'open' && phase === 'idle') { transition('confirming') }
        else if (role === 'cancel') cancel()
        else if (role === 'confirm' && phase === 'confirming' && clock() - opened >= 280 && settings.mode !== 'hold') {
            settings.mode === 'countdown' ? startCountdown() : void run('confirm')
        } else if (role === 'hold' && phase === 'error') void run('confirm')
        else if (role === 'hold' && phase === 'done') cancel()
        else if (role === 'undo' && phase === 'done' && settings.hasUndo) void run('undo')
        else if (role === 'retry' && phase === 'error') void run(attempt)
    }
    const keyDown = event => {
        if (event.key === 'Escape' && phase !== 'pending') { event.preventDefault(); event.stopPropagation(); cancel(); return }
        if (event.repeat) { if (['Enter', ' '].includes(event.key)) event.preventDefault(); return }
        const target = event.target.closest?.('[data-confirm-role]')
        if (event.key === 'Enter' && phase === 'countdown') { event.preventDefault(); void run('confirm'); return }
        if (['Enter', ' '].includes(event.key) && target &&
            (target.dataset.confirmRole === 'hold' || target.dataset.confirmRole === 'confirm' && settings.mode === 'hold')) startHold(event, target, 'keyboard')
    }
    const api = {
        init() {
            if (ready || scope.disposed) return ready
            ready = (async () => {
                if (!await waitForAssetActivation(this.$el, { signal: scope.signal }) || scope.disposed) return
                host = this.$el; doc = host.ownerDocument; win = doc.defaultView
                pill = host.querySelector('[data-morph-pill]')
                // Preserve the first interaction while this instance's optional motion chunk loads.
                let openAfterInit = false
                const stopEarlyClick = settings.variant === 'morph' ? scope.listen(host, 'click', event => {
                    const button = event.target.closest?.('[data-confirm-role="open"]')
                    if (button && host.contains(button) && !button.disabled && !settings.disabled) {
                        event.preventDefault(); event.stopPropagation(); openAfterInit = true
                    }
                }) : null
                const module = settings.variant === 'morph' ? await import('./motion.js') : null
                if (scope.disposed) return
                stopEarlyClick?.()
                motion = module ? module.createMorphMotion(pill, scope) : { show() {}, resize() {}, destroy() {} }
                countdownView = createCountdownPresentation(host, scope, win.matchMedia?.('(prefers-reduced-motion: reduce)'))
                holdFeedback = createHoldFeedback(host, settings)
                hold = createHold({ scope, duration: settings.duration, releaseDuration: settings.releaseDuration, sweep: settings.sweep, paint, complete: completeHold, clock })
                scope.listen(host, 'click', click)
                scope.listen(host, 'pointerdown', event => {
                    const target = event.target.closest?.('[data-confirm-role]')
                    if (target && (target.dataset.confirmRole === 'hold' || target.dataset.confirmRole === 'confirm' && settings.mode === 'hold')) startHold(event, target, 'pointer')
                })
                scope.listen(host, 'keydown', keyDown)
                scope.listen(host, 'keyup', event => {
                    if (suppressedKey === event.key) { event.preventDefault(); suppressedKey = null }
                    if (pressed?.kind === 'keyboard' && pressed.id === event.key) cancelHold(false)
                })
                scope.listen(host, 'focusout', event => { if (hold.active && !host.contains(event.relatedTarget)) cancelHold(true) })
                scope.listen(host, 'contextmenu', event => { if (event.target.closest?.('[data-confirm-role="hold"], [data-confirm-role="confirm"]')) event.preventDefault() })
                if (settings.variant === 'morph') {
                scope.listen(doc, 'visibilitychange', () => { if (doc.hidden) { cancelHold(true); cancelDigit?.(); cancelDigit = null } syncTimers(); if (!doc.hidden && phase === 'countdown') { cancelDigit?.(); updateDigits() } })
                scope.listen(doc, 'pointerdown', event => { if (settings.cancelOnOutsidePress && ['confirming', 'countdown'].includes(phase) && !host.contains(event.target)) cancel() })
                }
                scope.listen(host, 'pointerenter', () => { hovered = true; syncTimers() })
                scope.listen(host, 'pointerleave', () => { hovered = false; syncTimers() })
                const Observer = win.MutationObserver
                if (Observer) {
                    const observer = new Observer(() => {
                        active = isAssetElementActive(host, { document: doc })
                        if (!active) { cancelHold(true); if (phase !== 'pending') cancel(); deadline.pause(true); countdown.pause(true) }
                        else syncTimers()
                    })
                    for (let parent = host.parentElement; parent; parent = parent.parentElement) {
                        observer.observe(parent, { attributes: true, attributeFilter: ['class', 'style', 'hidden', 'aria-hidden', 'data-fff-asset-active', 'open'] })
                        if (parent === doc.body) break
                    }
                    scope.trackObserver(observer)
                }
                scope.defer(() => { generation++; releasePress(); fillAnimation?.cancel(); stopCountdown() })
                paint(0, false, false)
                if (openAfterInit && live()) transition('confirming')
            })()
            return ready
        },
        destroy() { scope.dispose() },
        getResourceSnapshot: () => ({ ...scope.snapshot(), phase, holding: hold?.active ?? false }),
    }
    return api
}
