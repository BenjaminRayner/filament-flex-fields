import { createOwnedSubscriptions } from './callback-effects.js'
import { createResourceScope } from './resource-scope.js'
import { observeEffectResult, reportEffectFailure } from './effect-errors.js'

const namePattern = /^[a-z][a-z0-9-]*\.[a-z][a-z0-9-]*:[a-z][a-z0-9.-]*(?![\s\S])/
const plain = value => value !== null && typeof value === 'object'
    && [Object.prototype, null].includes(Object.getPrototypeOf(value))

const snapshot = (value, limit) => {
    if (!plain(value)) throw Error('FLEX event metadata must be a plain record.')
    const keys = Reflect.ownKeys(value)
    if (keys.length > limit) throw Error('FLEX event metadata exceeds its budget.')
    const copy = Object.create(Object.getPrototypeOf(value))
    for (const key of keys) {
        const descriptor = Object.getOwnPropertyDescriptor(value, key)
        if (typeof key !== 'string' || !descriptor?.enumerable || !Object.hasOwn(descriptor, 'value')) {
            throw Error('FLEX event metadata must contain enumerable data properties.')
        }
        Object.defineProperty(copy, key, { value: descriptor.value, enumerable: true })
    }
    return Object.freeze(copy)
}

/** Instance-local events. Notifications never decide whether an operation may run. */
export function createLifecycleEvents({ events, signal, limit = 4096, onError = () => {} } = {}) {
    if (!plain(events) || !Object.keys(events).length || Object.keys(events).length > 128
        || Reflect.ownKeys(events).length !== Object.keys(events).length || typeof onError !== 'function'
        || !Number.isSafeInteger(limit) || limit < 1 || limit > 4096) {
        throw Error('Invalid FLEX lifecycle event declarations.')
    }
    const declarations = new Map()
    for (const [name, source] of Object.entries(snapshot(events, 128))) {
        const descriptor = snapshot(source, 2)
        if (name.length > 128 || !namePattern.test(name) || !plain(descriptor)
            || Reflect.ownKeys(descriptor).some(key => !['kind', 'validate'].includes(key))
            || !['notification', 'guard'].includes(descriptor.kind) || typeof descriptor.validate !== 'function') {
            throw Error('Invalid FLEX namespaced lifecycle contract.')
        }
        declarations.set(name, Object.freeze({ kind: descriptor.kind, validate: descriptor.validate }))
    }
    // Reports contain bounded contract metadata only, never detail, errors supplied
    // by application code, element references, field values or signed URLs.
    const report = (name, reason) => reportEffectFailure(onError, Object.freeze({ name, reason }))
    const resources = createResourceScope({ signal, limit: limit + 2, onCleanupError: () => report('', 'cleanup-failed') })
    const subscriptions = createOwnedSubscriptions({ names: [...declarations.keys()], limit,
        reportFailure: (error, name) => report(name, 'callback-failed') })
    resources.defer(subscriptions.dispose)
    const dispatches = new Map()
    const declaration = (name, kind) => {
        const entry = declarations.get(name)
        if (!entry || (kind && entry.kind !== kind)) throw Error('Unknown FLEX lifecycle event or incorrect dispatch kind.')
        return entry
    }
    const dispatch = (name, detail, kind) => {
        const entry = declaration(name, kind)
        if (resources.disposed) return false
        const previous = dispatches.get(name)
        if (previous || dispatches.size >= 32) {
            if (previous) previous.vetoed = true
            report(name, 'reentrant-dispatch')
            return false
        }
        const current = { vetoed: false }
        dispatches.set(name, current)
        try {
            const payload = snapshot(detail, 64)
            let valid = false
            {
                const result = entry.validate(payload)
                if (typeof result?.then === 'function') {
                    observeEffectResult(result, () => {})
                } else valid = result === true
            }
            if (!valid || current.vetoed || resources.disposed) { report(name, 'invalid-payload'); return false }
            // Shallow copy/freeze prevents one subscriber changing top-level fields
            // for another. Nested payload types/immutability belong to the validator.
            const context = Object.freeze({ name, signal: resources.signal })
            if (kind === 'notification') {
                subscriptions.notify(name, [payload, context])
                return !resources.disposed && !current.vetoed
            }
            const protectedResult = subscriptions.protectedBy(name, [payload, context])
            return !protectedResult && !current.vetoed && !resources.disposed
        } catch {
            report(name, 'invalid-payload')
            return false
        } finally { dispatches.delete(name) }
    }
    return Object.freeze({
        subscribe(name, callback, { signal: interestSignal } = {}) {
            const entry = declaration(name)
            if (typeof callback !== 'function') throw Error('FLEX lifecycle callback must be a function.')
            if (resources.disposed) throw Error('FLEX lifecycle events are disposed.')
            const interest = createResourceScope({ signal: interestSignal })
            if (interest.disposed) return () => {}
            let release = () => {}
            try {
                const stop = subscriptions.subscribe({ [name]: (payload, context) => {
                    if (interest.disposed) return entry.kind === 'guard' ? true : undefined
                    if (entry.kind === 'notification') return callback(payload, context)
                    const result = callback(payload, context)
                    if (typeof result?.then === 'function') {
                        observeEffectResult(result, () => {})
                        report(name, 'asynchronous-guard')
                        return true
                    }
                    if (typeof result !== 'boolean') { report(name, 'invalid-guard'); return true }
                    return !result
                } })
                interest.defer(stop)
                // An aborted individual subscription must also leave the parent
                // resource set immediately, without waiting for component removal.
                const aborted = () => release()
                interest.signal.addEventListener('abort', aborted, { once: true })
                interest.defer(() => interest.signal.removeEventListener('abort', aborted))
                release = resources.defer(interest.dispose)
                return release
            } catch (error) { interest.dispose(); throw error }
        },
        notify: (name, detail) => dispatch(name, detail, 'notification'),
        allow: (name, detail) => dispatch(name, detail, 'guard'),
        snapshot: () => Object.freeze({ disposed: resources.disposed, ownedResources: resources.snapshot().resources,
            ...subscriptions.snapshot() }),
        dispose: resources.dispose,
    })
}
