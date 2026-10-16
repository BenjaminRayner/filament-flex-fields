import { createOwnedAttempts } from '../lifecycle/owned-attempts.js'
import { createResourceScope } from '../lifecycle/resource-scope.js'
import { createOwnerTreeIndex } from '../owners/owner-tree-index.js'
import { observeEffectResult, reportEffectFailure } from '../lifecycle/effect-errors.js'
import { attachCustomSurfaces } from './document-surfaces.js'
import { createSurfaceHandle } from './surface-handle.js'

/** Headless surface ownership. Presentation, focus and scroll locking belong to the host. */
export function createCustomSurfaces({ document, signal, prepare, release, retire, isActive = () => true, isReservedRoot = () => false, reportFailure = () => {}, limit = 256 }) {
    if (!document || typeof prepare !== 'function' || typeof release !== 'function' || typeof retire !== 'function'
        || typeof isActive !== 'function' || typeof isReservedRoot !== 'function' || !Number.isSafeInteger(limit) || limit < 1 || limit > 4096) throw Error('Invalid FLEX custom surface adapter.')
    if (signal?.aborted) throw new DOMException('FLEX surface adapter lifetime ended.', 'AbortError')
    const lifetime = createResourceScope({ signal, onCleanupError: reportFailure })
    const attempts = createOwnedAttempts({ signal: lifetime.signal, limit })
    const roots = new WeakMap(), handles = new WeakMap(), records = new Set(), owners = createOwnerTreeIndex()
    let sequence = 0
    lifetime.defer(attempts.dispose)

    const find = element => {
        for (let node = element; node; node = node.parentElement) {
            const record = roots.get(node)
            if (record && !record.disposed) return record
        }
        return null
    }
    const parentOf = record => record.parent ?? find(record.root.parentElement)
    const retained = record => {
        if (!record) return false
        const visited = new Set()
        for (let current = record; current; current = parentOf(current)) {
            if (current.disposed || current.root.isConnected === false || current.state === 'closed' || visited.has(current)) return false
            visited.add(current)
        }
        return true
    }
    const effect = (callback, ...args) => {
        try { observeEffectResult(callback(...args), reportFailure) }
        catch (error) { reportEffectFailure(reportFailure, error) }
    }
    const descendants = record => {
        const affected = []
        for (const candidate of records) {
            if (record.root.contains(candidate.root)) { affected.push(candidate); continue }
            const visited = new Set()
            for (let current = candidate; current && !visited.has(current); current = parentOf(current)) {
                if (current === record) { affected.push(candidate); break }
                visited.add(current)
            }
        }
        return affected.reverse()
    }
    const closeOne = record => {
        record.state = 'closed'; record.ready = false
        attempts.cancel(record)
        record.root.setAttribute('data-fff-asset-active', 'false')
        effect(release, record.root)
    }
    const close = record => {
        if (record.disposed) return
        for (const child of descendants(record)) closeOne(child)
        effect(retire)
    }
    const removeOne = record => {
        if (record.disposed) return
        closeOne(record)
        record.disposed = true; records.delete(record); owners.remove(record)
        roots.delete(record.root); handles.delete(record.handle)
        if (record.detachAbort) effect(record.detachAbort)
        record.detachAbort = null
        record.parent = null
        record.root = null; record.handle = null; record.actions = null
    }
    const remove = record => {
        if (record.disposed) return
        for (const child of descendants(record)) removeOne(child)
        effect(retire)
    }
    const register = (root, { parent = null, signal: ownerSignal = null } = {}) => {
        if (lifetime.disposed || ownerSignal?.aborted) throw new DOMException('FLEX surface lifetime ended.', 'AbortError')
        if (root?.nodeType !== 1 || root.ownerDocument !== document || root.isConnected === false
            || isReservedRoot(root) || roots.has(root)) throw Error('Invalid or duplicate FLEX surface root.')
        if (ownerSignal && (typeof ownerSignal.aborted !== 'boolean' || typeof ownerSignal.addEventListener !== 'function'
            || typeof ownerSignal.removeEventListener !== 'function')) throw Error('Invalid FLEX surface owner signal.')
        if (records.size >= limit) throw Error('FLEX custom surface budget exhausted.')
        const parentRecord = parent === null ? null : handles.get(parent)
        if (parent !== null && (!parentRecord || parentRecord.disposed || root.contains(parentRecord.root))) throw Error('Invalid FLEX surface parent.')
        const record = { root, parent: parentRecord, state: 'closed', ready: false, disposed: false,
            key: `surface:custom-${++sequence}`, handle: null, detachAbort: null, pending: null, actions: null }
        const assertLive = () => {
            if (lifetime.disposed || record.disposed || root.isConnected === false) throw new DOMException('FLEX surface lifetime ended.', 'AbortError')
        }
        const activate = () => {
            assertLive()
            const ancestor = parentOf(record)
            if (ancestor && !retained(ancestor)) return Promise.resolve(false)
            if (record.pending) return record.pending
            record.state = 'active'; record.ready = false
            root.setAttribute('data-fff-asset-active', 'true')
            const attempt = attempts.begin(record)
            const current = () => attempt.current() && record.state === 'active' && retained(record) && isActive(root) === true
            // Cancellation settles the host's interest immediately; shared asset IO is retained.
            const pending = new Promise((resolve, reject) => {
                let settled = false
                const finish = (value, error = null) => {
                    if (settled) return
                    settled = true; attempt.signal.removeEventListener('abort', aborted)
                    if (record.pending === pending) record.pending = null
                    attempt.dispose()
                    if (error) reject(error)
                    else resolve(value)
                }
                const aborted = () => finish(false)
                attempt.signal.addEventListener('abort', aborted, { once: true })
                Promise.resolve().then(() => current() ? prepare(root, attempt.signal) : false).then(ready => {
                    if (!current()) return finish(false)
                    if (ready !== true) return finish(false)
                    record.ready = true; finish(true)
                }, error => {
                    if (!current()) return finish(false)
                    record.ready = false
                    reportEffectFailure(reportFailure, error, { ownerId: record.key })
                    finish(false, error)
                })
            })
            record.pending = pending
            return pending
        }
        record.actions = {
            activate,
            suspend() { assertLive(); if (record.state === 'closed') return; record.state = 'suspended'; attempts.cancel(record) },
            close: () => close(record),
            dispose: () => remove(record),
            ready: () => record.ready && record.state === 'active' && retained(record) && isActive(root) === true,
        }
        const handle = createSurfaceHandle(record)
        record.handle = handle
        roots.set(root, record); handles.set(handle, record); records.add(record); owners.bind(record, root)
        root.setAttribute('data-fff-asset-active', 'false')
        if (ownerSignal) {
            const abort = () => remove(record)
            record.detachAbort = () => ownerSignal.removeEventListener('abort', abort)
            try { ownerSignal.addEventListener('abort', abort, { once: true }) }
            catch (error) { remove(record); throw error }
            if (ownerSignal.aborted) remove(record)
        }
        return handle
    }
    const api = Object.freeze({ register,
        keyForRoot: root => roots.get(root)?.key ?? null,
        retainsRoot(root) { const record = roots.get(root); return record ? retained(record) : false },
        isActiveFor(element) { if (!records.size) return null; const record = find(element); return record ? retained(record) : null },
        prune(root = null) {
            for (const record of root?.nodeType === 1 ? owners.within(root) : [...records]) {
                if (record.disposed) continue
                if (record.root.isConnected === false) remove(record)
                else owners.bind(record, record.root)
            }
        },
        snapshot: () => ({ surfaces: records.size, active: [...records].filter(record => record.state === 'active').length,
            suspended: [...records].filter(record => record.state === 'suspended').length, attempts: attempts.snapshot().attempts, limit }),
        dispose: lifetime.dispose,
    })
    lifetime.defer(() => { for (const record of [...records]) removeOne(record); owners.clear() })
    try { lifetime.defer(attachCustomSurfaces(document, api)) }
    catch (error) { lifetime.dispose(); throw error }
    return api
}
