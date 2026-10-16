import { createResourceScope } from './resource-scope.js'
import { observeEffectResult, reportEffectFailure } from './effect-errors.js'

const entries = value => {
    if (!value || ![Object.prototype, null].includes(Object.getPrototypeOf(value))) throw Error('Invalid FLEX hook record.')
    return Reflect.ownKeys(value).map(name => {
        const descriptor = Object.getOwnPropertyDescriptor(value, name)
        if (typeof name !== 'string' || !descriptor.enumerable || !Object.hasOwn(descriptor, 'value')) throw Error('Invalid FLEX hook record.')
        return [name, descriptor.value]
    })
}

/** Latest live override wins; registrations and decisions belong to their owner. */
export function createOwnedHookOverrides({ defaults, types = {}, limit = 4096, reportFailure = () => {}, signal }) {
    const initial = entries(defaults), expected = new Map(entries(types))
    if (!Number.isInteger(limit) || limit < 1 || limit > 4096 || typeof reportFailure !== 'function'
        || initial.length < 1 || initial.length > 128
        || initial.some(([name, callback]) => !/^[a-zA-Z][a-zA-Z0-9]{0,127}$/.test(name) || typeof callback !== 'function')
        || [...expected].some(([name, type]) => !initial.some(([key]) => key === name) || !['boolean', 'number', 'string', 'undefined'].includes(type))) {
        throw Error('Invalid FLEX hook contract or budget.')
    }
    const report = (error, name) => reportEffectFailure(reportFailure, error, name)
    const resources = createResourceScope({ signal, onCleanupError: error => report(error, '') })
    const slots = new Map(initial.map(([name, fallback]) => [name, { tail: null, fallback }]))
    const registrations = new Set()
    const dispatching = new Set()
    const hooks = Object.freeze(Object.fromEntries([...slots].map(([name, slot]) => [name, (...args) => {
        // A recursive override must not overflow the stack or silently reinvoke
        // itself. Its outer invocation catches this and runs the core default.
        if (dispatching.has(name)) throw Error('Reentrant FLEX runtime hook.')
        const current = slot.tail, callback = current?.callback
        if (!callback || resources.disposed) return slot.fallback(...args)
        dispatching.add(name)
        try {
            const result = callback(...args)
            if (typeof result?.then === 'function') {
                observeEffectResult(result, () => {})
                throw Error('FLEX runtime hooks must be synchronous.')
            }
            const type = expected.get(name)
            if (type && (typeof result !== type || (type === 'number' && !Number.isFinite(result)))) throw Error('Invalid FLEX runtime hook result.')
            // A callback can close its own owner. Its obsolete decision must not
            // govern another owner's operation after that cancellation.
            if (resources.disposed || slot.tail !== current || !current.callback) return slot.fallback(...args)
            return result
        } catch (error) {
            report(error, name)
            return slot.fallback(...args)
        } finally { dispatching.delete(name) }
    }])))

    const register = (partial = {}, { signal: interestSignal } = {}) => {
        if (resources.disposed) throw Error('FLEX hooks are disposed.')
        const overrides = entries(partial)
        if (overrides.some(([name, callback]) => !slots.has(name) || typeof callback !== 'function')) throw Error('Invalid FLEX hook overrides.')
        const interest = createResourceScope({ signal: interestSignal })
        if (interest.disposed || !overrides.length) { interest.dispose(); return () => {} }
        if (registrations.size >= limit) { interest.dispose(); throw Error('FLEX hook budget exhausted.') }
        const nodes = overrides.map(([name, callback]) => {
            const slot = slots.get(name), node = { slot, callback, previous: slot.tail, next: null }
            if (slot.tail) slot.tail.next = node
            slot.tail = node
            return node
        })
        interest.defer(() => {
            registrations.delete(release)
            for (const node of nodes) {
                if (node.previous) node.previous.next = node.next
                if (node.next) node.next.previous = node.previous
                if (node.slot.tail === node) node.slot.tail = node.previous
                node.callback = null; node.previous = null; node.next = null
            }
        })
        let release = () => {}
        const aborted = () => release()
        interest.signal.addEventListener('abort', aborted, { once: true })
        interest.defer(() => interest.signal.removeEventListener('abort', aborted))
        release = resources.defer(interest.dispose)
        registrations.add(release)
        return release
    }
    return Object.freeze({ hooks, register, snapshot: () => ({ registrations: registrations.size, limit }), dispose: resources.dispose })
}
