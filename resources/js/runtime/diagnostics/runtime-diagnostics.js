import { createOwnedSubscriptions } from '../lifecycle/owned-subscriptions.js'

const PHASES = new Set(['boot', 'transport', 'preparation', 'evaluation', 'init', 'ownership', 'cleanup', 'callback', 'observer'])
const CODES = new Set(['asset_load_failed', 'asset_policy_failed', 'asset_timeout', 'asset_cancelled', 'owner_plan_failed', 'module_import_failed', 'factory_init_failed', 'cleanup_failed', 'callback_failed', 'callback_contract_failed', 'observer_failed'])
const read = (object, key) => { try { return object?.[key] } catch { return undefined } }
const MARKS = new Set(['fff:load', 'fff:acquire', 'fff:release', 'fff:uninstall', 'fff:barrier'])

/** Strict projection: never copy errors, messages, URLs, form values or arbitrary detail. */
export function createRuntimeDiagnostics({ performance, emit = () => {}, capacity = 256, identityLimit = 512, subscriberLimit = 128 } = {}) {
    if (![capacity, identityLimit, subscriberLimit].every(value => Number.isSafeInteger(value) && value > 0 && value <= 65536)) {
        throw Error('Invalid FLEX diagnostics budget.')
    }
    const ring = new Array(capacity), identities = { owner: new Map(), plan: new Map() }, marks = new Set()
    let sequence = 0, count = 0, position = 0, identitySequence = 0, disposed = false
    const identity = (kind, value) => {
        if (typeof value !== 'string' || !value || value.length > 8192) return null
        const values = identities[kind]
        if (values.has(value)) return values.get(value)
        const alias = `${kind}-${++identitySequence}`
        if (values.size >= identityLimit) values.delete(values.keys().next().value)
        values.set(value, alias)
        return alias
    }
    const project = context => {
        const result = {}
        const component = read(context, 'componentId')
        if (typeof component === 'string' && /^[a-z][a-z0-9:._-]{0,127}$/.test(component)) result.componentId = component
        for (const kind of ['owner', 'plan']) {
            const alias = identity(kind, read(context, `${kind}Id`))
            if (alias) result[`${kind}Id`] = alias
        }
        const kind = read(context, 'kind')
        if (['stylesheet', 'chunk'].includes(kind)) result.kind = kind
        const amount = read(context, 'count')
        if (Number.isSafeInteger(amount) && amount >= 0 && amount <= 100000) result.count = amount
        return result
    }
    const record = (code, phase, context, error = null, notify = true) => {
        if (disposed || read(error, 'name') === 'AbortError') return null
        const entry = Object.freeze({ sequence: ++sequence, code: CODES.has(code) ? code : 'asset_load_failed',
            phase: PHASES.has(phase) ? phase : 'preparation', ...project(context) })
        ring[position] = entry; position = (position + 1) % capacity; count = Math.min(count + 1, capacity)
        if (notify) {
            subscribers.notify('record', [entry])
            try { emit(entry) } catch { record('observer_failed', 'observer', {}, null, false) }
        }
        return entry
    }
    const subscribers = createOwnedSubscriptions({ names: ['record'], limit: subscriberLimit,
        reportFailure: () => record('observer_failed', 'observer', {}, null, false) })
    return {
        report: (error, context = {}) => record(read(context, 'code'), read(context, 'phase'), context, error),
        identifyOwner: value => disposed ? null : identity('owner', value),
        identifyPlan: value => disposed ? null : identity('plan', value),
        subscribe: callback => subscribers.subscribe({ record: callback }),
        mark(name, context = {}) {
            if (disposed || !MARKS.has(name) || typeof performance?.mark !== 'function' || typeof performance?.clearMarks !== 'function') return
            try {
                performance.clearMarks(name)
                marks.add(name)
                performance.mark(name, { detail: Object.freeze(project(context)) })
            } catch { /* Optional User Timing never controls readiness. */ }
        },
        snapshot: () => ({ records: Array.from({ length: count }, (_, i) => ring[(position - count + i + capacity) % capacity]),
            capacity, sequence, subscribers: subscribers.snapshot().groups, identities: identities.owner.size + identities.plan.size, identityLimit, marks: marks.size }),
        dispose() {
            if (disposed) return
            disposed = true
            subscribers.dispose(); ring.fill(undefined); count = 0
            for (const name of marks) { try { performance?.clearMarks?.(name) } catch {} }
            marks.clear(); identities.owner.clear(); identities.plan.clear()
        },
    }
}
