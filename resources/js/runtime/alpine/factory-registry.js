import { factoryIdentity, sameFactoryIdentity } from './factory-identity.js'

/** One global alias has one source factory. Readiness may decorate that source;
 * no other owner or function can replace it. Registration validates before writes.
 */
export function createFactoryRegistry({ factories = new Map(), owners = new Map(), limit = 4096 } = {}) {
    if (!Number.isSafeInteger(limit) || limit < 1 || limit > 16384 || !(factories instanceof Map) || !(owners instanceof Map)
        || factories.size > limit || owners.size > limit) throw new Error('Invalid FLEX factory registry budget or storage.')
    const claim = (name, source, descriptor = null) => {
        if (typeof name !== 'string' || name.length > 128 || !/^[A-Za-z_$][\w$]*(?![\s\S])/.test(name)
            || ['__proto__', 'constructor', 'prototype'].includes(name) || typeof source !== 'function') {
            throw new Error('FLEX factory registration requires a bounded Alpine name and callable source.')
        }
        const identity = descriptor === null ? null : factoryIdentity(descriptor)
        if ((!owners.has(name) && owners.size >= limit) || (!factories.has(name) && factories.size >= limit)) {
            throw new Error('FLEX factory registration budget exceeded.')
        }
        let record = owners.get(name)
        // An earlier transitional runtime may already have populated this map.
        if (!record && factories.has(name)) record = Object.freeze({ source: factories.get(name), identity: null })
        if (record) {
            if (identity === null && record.identity === null) {
                // Legacy anonymous re-registration remains idempotent, but never
                // replaces the first callback. Typed authors get strict conflicts.
                if (!owners.has(name)) owners.set(name, record)
                return record
            }
            if (record.source !== source || (record.identity && !sameFactoryIdentity(record.identity, identity))) {
                throw new Error(`FLEX factory alias conflict: ${name}. Use a unique package alias; the existing owner was preserved.`)
            }
            if (record.identity) return record
        }
        record = Object.freeze({ source, identity })
        owners.set(name, record)
        if (!factories.has(name)) factories.set(name, source)
        return record
    }
    const decorate = (name, source, descriptor, decorator) => {
        if (typeof decorator !== 'function') throw new Error('FLEX factory decorator must be callable.')
        const record = claim(name, source, descriptor)
        if (record.source !== source) throw new Error('Cannot decorate another FLEX factory source.')
        if (factories.get(name) !== source) return record
        const prepared = decorator(source)
        if (typeof prepared !== 'function') throw new Error('FLEX factory decorator must return a callable.')
        factories.set(name, prepared)
        return record
    }
    return { claim, decorate }
}
