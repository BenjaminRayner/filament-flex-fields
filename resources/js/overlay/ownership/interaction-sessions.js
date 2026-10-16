import { isEventWithinMenuSurface } from '../menu/dom.js'
import { shouldHandlePickerKey, canRestorePickerFocus } from '../keyboard-policy.js'

const serviceKey = Symbol.for('filament-flex-fields.overlay-interactions.v1')

/** One document listener pair arbitrates the innermost open owner, not binding order. */
export function createOverlayInteractionSessions(document) {
    const view = document?.defaultView
    if (!view?.addEventListener || !document?.documentElement) throw Error('Overlay interactions require a document and window.')
    const entries = new Set()
    let observer = null, listening = false
    const modalOf = node => node?.closest?.('dialog[open], [aria-modal="true"]') ?? null
    const resolve = entry => {
        try {
            if (!entry.current()) return null
            const anchor = entry.anchor(), panel = entry.panel()
            if (!anchor?.isConnected || anchor.ownerDocument !== document
                || (panel && (!panel.isConnected || panel.ownerDocument !== document))) return null
            return { entry, anchor, panel }
        } catch { return null }
    }
    const stop = () => {
        if (!listening || entries.size) return
        listening = false
        view.removeEventListener('keydown', keydown, true)
        view.removeEventListener('pointerdown', pointerdown, true)
        observer?.disconnect(); observer = null
    }
    const retire = entry => {
        if (!entries.delete(entry)) return
        // A caller may retain its disposer. Retired handles must not retain the
        // host/DOM through callbacks after explicit release or observer cleanup.
        entry.anchor = entry.panel = entry.current = entry.dismiss = entry.contains = entry.onError = null
        stop()
    }
    const prune = () => {
        for (const entry of entries) if (!resolve(entry)) retire(entry)
        stop()
    }
    const top = event => {
        prune()
        const target = event.composedPath?.()[0] ?? event.target
        const modal = modalOf(target) ?? modalOf(document.activeElement)
        let selected = null
        for (const entry of entries) {
            const candidate = resolve(entry)
            if (!candidate || candidate.anchor.closest?.('[inert], [aria-hidden="true"]')) continue
            // An unrelated/child modal owns its own dismissal and focus trap.
            if (modal && !modal.contains(candidate.anchor) && !modal.contains(candidate.panel)) continue
            if (!selected || selected.panel?.contains(candidate.anchor)) selected = candidate
            else if (!candidate.panel?.contains(selected.anchor)) selected = candidate
        }
        return selected
    }
    const dismiss = (owner, reason, event) => {
        if (owner.entry.closing) return
        owner.entry.closing = true
        const restore = reason === 'escape' && canRestorePickerFocus(owner.anchor, owner.panel, document.activeElement)
        try {
            owner.entry.dismiss(reason, event)
            if (restore && owner.anchor.isConnected && !owner.anchor.closest?.('[inert], [aria-hidden="true"]')) owner.anchor.focus?.({ preventScroll: true })
        } catch (error) {
            owner.entry.closing = false
            try { owner.entry.onError(error) } catch { /* Reporting cannot displace another owner. */ }
        }
    }
    function keydown(event) {
        if (event.key !== 'Escape' || !shouldHandlePickerKey(event)) return
        const owner = top(event)
        if (!owner) return
        event.preventDefault(); event.stopImmediatePropagation()
        dismiss(owner, 'escape', event)
    }
    function pointerdown(event) {
        if (event.defaultPrevented || (event.button != null && event.button !== 0)) return
        const owner = top(event)
        if (!owner || !owner.entry.outside || isEventWithinMenuSurface(event, owner.anchor, owner.panel)) return
        try { if (owner.entry.contains?.(event)) return } catch { return }
        dismiss(owner, 'outside', event)
    }
    return {
        register({ anchor, panel, current, dismiss, outside = false, contains, onError = () => {} }) {
            if ([anchor, panel, current, dismiss, onError].some(callback => typeof callback !== 'function')
                || (contains != null && typeof contains !== 'function') || typeof outside !== 'boolean') throw Error('Invalid overlay interaction session.')
            prune()
            if (entries.size >= 512) throw Error('Overlay interaction session budget exhausted.')
            const entry = { anchor, panel, current, dismiss, outside, contains, onError, closing: false }
            if (!resolve(entry)) throw Error('Overlay interaction owner is not connected and current.')
            entries.add(entry)
            if (!listening) {
                try {
                    listening = true
                    view.addEventListener('keydown', keydown, true)
                    view.addEventListener('pointerdown', pointerdown, true)
                    if (typeof view.MutationObserver === 'function') {
                        observer = new view.MutationObserver(records => {
                            if (records.some(record => record.removedNodes.length)) prune()
                        })
                        observer.observe(document.documentElement, { childList: true, subtree: true })
                    }
                } catch (error) { retire(entry); throw error }
            }
            return () => retire(entry)
        },
        snapshot: () => ({ sessions: entries.size, listening, observing: observer !== null }),
    }
}

/** Lazy bundles share the same document lifetime service without a second listener. */
export function documentOverlayInteractionSessions(document) {
    if (!document[serviceKey]) Object.defineProperty(document, serviceKey, { value: createOverlayInteractionSessions(document) })
    return document[serviceKey]
}
