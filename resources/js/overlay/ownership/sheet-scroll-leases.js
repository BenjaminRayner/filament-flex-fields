import { lockOverlaySheetScroll, unlockOverlaySheetScroll, OVERLAY_SHEET_OWNERS_KEY } from '../../core/overlay-sheet-scroll-lock.js'

/** Each sheet releases its own lock; removing a parent cannot unlock a live child. */
export function acquireSheetScrollLease({ document, window = document?.defaultView, anchor, panel, current }) {
    if (!document?.documentElement || !window || (document.defaultView && window !== document.defaultView)
        || [anchor, panel, current].some(callback => typeof callback !== 'function')) {
        throw Error('Invalid sheet scroll owner.')
    }
    let service = document[OVERLAY_SHEET_OWNERS_KEY]
    if (!service) {
        const entries = new Set()
        let observer = null
        const resolve = entry => {
            try {
                if (!entry.current()) return null
                const anchor = entry.anchor(), panel = entry.panel()
                if (!anchor?.isConnected || !panel?.isConnected || anchor.ownerDocument !== document || panel.ownerDocument !== document) return null
                return { anchor, panel }
            } catch { return null }
        }
        const release = entry => {
            if (!entries.delete(entry)) return
            entry.anchor = entry.panel = entry.current = null
            unlockOverlaySheetScroll(document, window)
            if (!entries.size) { observer?.disconnect(); observer = null }
        }
        const prune = () => { for (const entry of entries) if (!resolve(entry)) release(entry) }
        service = {
            acquire(options) {
                prune()
                if (entries.size >= 512) throw Error('Sheet scroll owner budget exhausted.')
                const entry = { ...options }
                if (!resolve(entry)) throw Error('Sheet scroll owner is not connected and current.')
                lockOverlaySheetScroll(document, window)
                entries.add(entry)
                try {
                    if (!observer && typeof window.MutationObserver === 'function') {
                        observer = new window.MutationObserver(records => {
                            if (records.some(record => record.removedNodes.length)) prune()
                        })
                        observer.observe(document.documentElement, { childList: true, subtree: true })
                    }
                } catch (error) { release(entry); throw error }
                return () => release(entry)
            },
            topPanel() {
                prune()
                let selected = null
                for (const entry of entries) {
                    const next = resolve(entry)
                    if (!next) continue
                    if (!selected || selected.panel.contains(next.anchor) || !next.panel.contains(selected.anchor)) selected = next
                }
                return selected?.panel ?? null
            },
            prune,
            snapshot: () => ({ owners: entries.size, observing: observer !== null }),
        }
        Object.defineProperty(document, OVERLAY_SHEET_OWNERS_KEY, { value: service })
    }
    return service.acquire({ anchor, panel, current })
}
