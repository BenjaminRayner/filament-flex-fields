import { createOwnerTreeIndex } from '../owners/owner-tree-index.js'

/** One dirty-root queue for mutation, morph and ownership work within a runtime. */
export function createDomTransactions({ window, prepare, ownership, limit = 4096 }) {
    if (!Number.isSafeInteger(limit) || limit < 1 || limit > 4096 || typeof prepare !== 'function' || typeof ownership !== 'function') throw Error('Invalid FLEX DOM transaction configuration.')
    const handlers = new Map([['ownership', { callback: () => {}, prepare: false, priority: -1, reconciles: true }]])
    const pending = new Map(), running = new Set(), index = createOwnerTreeIndex()
    let frame = null, disposed = false
    const covers = (parent, child) => parent === child || parent.contains?.(child)
    const flush = async () => {
        frame = null
        const entries = [...pending.values()]
        pending.clear(); index.clear()
        for (const entry of entries) running.add(entry)
        await Promise.all(entries.map(async entry => {
            try {
                if (!disposed && entry.root.isConnected !== false) {
                    const actions = [...entry.kinds].map(name => handlers.get(name)).filter(Boolean).sort((a, b) => a.priority - b.priority)
                    if (actions.some(action => action.prepare)) {
                        if (await prepare(entry.root) === false) { entry.resolve(false); return }
                    }
                    else if (actions.some(action => action.reconciles)) await ownership(entry.root)
                    for (const action of actions) {
                        if (disposed || entry.root.isConnected === false) break
                        await action.callback(entry.root)
                    }
                }
                entry.resolve()
            } catch (error) { entry.reject(error) }
            finally { running.delete(entry) }
        }))
    }
    const merge = (parent, child) => {
        for (const name of child.kinds) parent.kinds.add(name)
        // Each queued root has one completion promise, rather than one new
        // continuation per repeated notification about the same root.
        parent.promise.then(child.resolve, child.reject)
        pending.delete(child.root); index.remove(child.root)
    }
    return {
        register(name, callback, { prepares = true, reconciles = true, priority = 0 } = {}) {
            if (disposed || typeof name !== 'string' || !name || typeof callback !== 'function' || handlers.has(name) || handlers.size >= 128 || typeof prepares !== 'boolean' || typeof reconciles !== 'boolean' || !Number.isSafeInteger(priority)) throw Error('Invalid FLEX DOM transaction handler.')
            const action = { callback, prepare: prepares, reconciles, priority }
            handlers.set(name, action)
            let active = true
            return () => {
                if (!active) return
                active = false
                if (handlers.get(name) === action) handlers.delete(name)
                action.callback = () => {}
            }
        },
        schedule(root, kind = 'ownership') {
            if (disposed || !root) return Promise.resolve()
            if (!handlers.has(kind)) return Promise.reject(Error('Unknown FLEX DOM transaction kind.'))
            // Ancestor lookup is independent of the number of unrelated dirty roots.
            for (let node = root; node; node = node.parentNode ?? node.parentElement) {
                const ancestor = pending.get(node)
                if (ancestor) { ancestor.kinds.add(kind); return ancestor.promise }
            }
            const descendants = index.within(root).filter(childRoot => covers(root, childRoot))
            if (running.size + pending.size - descendants.length >= limit) return Promise.reject(Error('FLEX dirty-root budget exhausted.'))
            let resolve, reject
            const promise = new Promise((yes, no) => { resolve = yes; reject = no })
            const entry = { root, kinds: new Set([kind]), promise, resolve, reject }
            // Observe callers' rejection even when native callbacks ignore it.
            promise.catch(() => {})
            for (const childRoot of index.within(root)) {
                const child = pending.get(childRoot)
                if (child && covers(root, childRoot)) merge(entry, child)
                else if (child) index.bind(childRoot, childRoot)
            }
            pending.set(root, entry); index.bind(root, root)
            if (frame === null) frame = window.requestAnimationFrame(flush)
            return promise
        },
        snapshot: () => ({ roots: pending.size, running: running.size, handlers: handlers.size, limit }),
        dispose() {
            if (disposed) return
            disposed = true
            if (frame !== null) window.cancelAnimationFrame(frame)
            frame = null
            for (const entry of [...pending.values(), ...running]) entry.resolve()
            running.clear()
            pending.clear(); index.clear(); handlers.clear()
        },
    }
}
