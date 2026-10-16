import { parseCivilDate } from '../domain/civil-date.js'

/** A bounded cache belongs to one field; late replies never repaint another month. */
export function createCalendarAvailability(request, enabled, changed) {
    const cache = new Map()
    let epoch = 0, current = null, disposed = false
    return {
        get pending() { return enabled && current?.status !== 'ready' },
        get error() { return current?.status === 'error' },
        unavailable(value) { return current?.dates.has(value) ?? false },
        load(start, end, retry = false, values = null) {
            if (!enabled || disposed) return
            const key = values ? values.join(',') : `${start}/${end}`, ticket = ++epoch, entry = cache.get(key)
            if (!retry && entry && Date.now() - entry.created < 30000) { current = entry; changed(); return }
            current = { status: 'loading', dates: new Set() }; changed()
            Promise.resolve().then(() => request(start, end, values)).then(response => {
                if (disposed || ticket !== epoch) return
                if (response?.status !== 'ready' || response.start !== start || response.end !== end || !Array.isArray(response.unavailable)
                    || response.unavailable.length > 93 || response.unavailable.some(date => !parseCivilDate(date) || date < start || date > end || (values && !values.includes(date)))) throw new Error('Invalid calendar availability response.')
                current = { status: 'ready', dates: new Set(response.unavailable), created: Date.now() }
                cache.delete(key); cache.set(key, current)
                if (cache.size > 6) cache.delete(cache.keys().next().value)
                changed()
            }).catch(() => { if (!disposed && ticket === epoch) { current = { status: 'error', dates: new Set() }; changed() } })
        },
        suspend() { epoch++; current = null },
        dispose() { disposed = true; epoch++; cache.clear(); current = null },
    }
}
