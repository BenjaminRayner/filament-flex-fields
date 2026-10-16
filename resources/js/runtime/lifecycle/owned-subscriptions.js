import { observeEffectResult, reportEffectFailure } from './effect-errors.js'

/** Independent registrations; callbacks shared by two owners are still two interests. */
export function createOwnedSubscriptions({ names, limit = 4096, reportFailure = () => {} }) {
    if (!Array.isArray(names) || !names.length || new Set(names).size !== names.length
        || !Number.isSafeInteger(limit) || limit < 1) throw Error('Invalid FLEX subscription budget.')
    const channels = new Map(names.map(name => [name, new Set()]))
    const groups = new Set()
    let disposed = false
    const report = (error, channel, context) => {
        reportEffectFailure(reportFailure, error, channel, context)
    }
    const subscribe = options => {
        if (disposed) throw Error('FLEX subscriptions are disposed.')
        if (!options || typeof options !== 'object' || Array.isArray(options)
            || Object.keys(options).some(name => !channels.has(name))) throw Error('Invalid FLEX subscription channels.')
        const entries = Object.entries(options).filter(([, callback]) => callback !== undefined && callback !== null)
        if (entries.some(([, callback]) => typeof callback !== 'function')) throw Error('FLEX subscribers must be functions.')
        if (groups.size >= limit) throw Error('FLEX subscription budget exhausted.')
        const records = entries.map(([channel, callback]) => ({ channel, callback, active: true }))
        let active = true
        const unsubscribe = () => {
            if (!active) return
            active = false
            groups.delete(unsubscribe)
            for (const record of records) {
                record.active = false
                record.callback = null
                channels.get(record.channel).delete(record)
            }
        }
        if (records.length) groups.add(unsubscribe)
        for (const record of records) channels.get(record.channel).add(record)
        return unsubscribe
    }
    const notify = (channel, args = [], context = {}) => {
        if (disposed) return
        for (const record of [...(channels.get(channel) ?? [])]) {
            if (!record.active) continue
            try {
                const result = record.callback(...args)
                observeEffectResult(result, error => report(error, channel, context), () => record.active && !disposed)
            } catch (error) { if (record.active) report(error, channel, context) }
        }
    }
    const protectedBy = (channel, args = [], context = {}) => {
        if (disposed) return false
        let protectedResult = false
        for (const record of [...(channels.get(channel) ?? [])]) {
            if (!record.active) continue
            try {
                const result = record.callback(...args)
                if (result?.then) {
                    // Protection is synchronous. Treat async/invalid decisions as
                    // protected, observe their rejection and report the contract.
                    Promise.resolve(result).catch(() => {})
                    report(Error('FLEX protection predicate must be synchronous.'), channel, context)
                    protectedResult = true
                } else if (typeof result !== 'boolean') {
                    report(Error('FLEX protection predicate must return a boolean.'), channel, context)
                    protectedResult = true
                } else protectedResult ||= result
            } catch (error) { report(error, channel, context); protectedResult = true }
        }
        return protectedResult
    }
    return {
        subscribe, notify, protectedBy,
        snapshot: () => ({ groups: groups.size, channels: Object.fromEntries([...channels].map(([name, records]) => [name, records.size])), limit }),
        dispose() {
            if (disposed) return
            disposed = true
            for (const unsubscribe of [...groups]) unsubscribe()
        },
    }
}
