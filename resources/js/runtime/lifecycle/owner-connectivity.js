import { observeEffectResult } from './effect-errors.js'

const key = Symbol.for('filament-flex-fields.owner-connectivity.v1')

/** One removal observer per document; moving a connected owner is not disposal. */
export function documentOwnerConnectivity(document) {
    if (document[key]) return document[key]
    const entries = new Set()
    const view = document.defaultView
    let observer = null
    const stop = () => {
        if (entries.size) return
        observer?.disconnect()
        observer = null
    }
    const retire = (entry, disconnected = false) => {
        if (!entries.delete(entry)) return
        const callback = disconnected ? entry.disconnect : null
        entry.owner = entry.disconnect = null
        stop()
        // A broken owner cannot prevent disposal of other detached owners.
        try { observeEffectResult(callback?.(), () => {}) } catch { /* Owner cleanup is isolated. */ }
    }
    const connected = owner => owner?.isConnected && owner.ownerDocument === document
    const prune = () => {
        for (const entry of [...entries]) if (!connected(entry.owner)) retire(entry, true)
    }
    const service = {
        register(owner, disconnect) {
            if (!connected(owner) || typeof disconnect !== 'function') throw Error('FLEX lifetime requires a connected document owner and cleanup.')
            if (entries.size >= 4096) throw Error('FLEX document owner lifetime budget exhausted.')
            const entry = { owner, disconnect }
            entries.add(entry)
            try {
                if (!observer) {
                    observer = new view.MutationObserver(records => {
                        // No style, geometry, animation-frame or scroll work. One
                        // bounded owner pass after a batch that removed DOM nodes.
                        if (records.some(record => record.removedNodes.length)) prune()
                    })
                    observer.observe(document.documentElement, { childList: true, subtree: true })
                }
            } catch (error) { retire(entry); throw error }
            return () => retire(entry)
        },
        snapshot: () => ({ owners: entries.size, observing: observer !== null }),
    }
    Object.defineProperty(document, key, { value: service })
    return service
}

/** DOM-less adapters rely on their explicit destroy boundary. */
export function observeOwnerConnectivity(owner, disconnect) {
    const document = owner?.ownerDocument
    if (!document?.defaultView?.MutationObserver) return () => {}
    return documentOwnerConnectivity(document).register(owner, disconnect)
}
