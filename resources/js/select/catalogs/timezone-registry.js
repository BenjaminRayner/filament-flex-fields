import { createDocumentRegistry } from './document-registry.js'
import { mergeCompactPools } from './compact-pool-merge.js'
const registry = createDocumentRegistry({selector: '#fff-timezone-registry-data', merge: payloads => mergeCompactPools(payloads, {
    validKey: key => /^[A-Za-z][A-Za-z0-9_+./-]{0,255}$/.test(key),
    normalize: entry => typeof entry.l === 'string' ? {l: entry.l, o: typeof entry.o === 'string' ? entry.o : '', r: typeof entry.r === 'string' ? entry.r : ''} : null,
    label: entry => entry.l,
})})
export const resetTimezoneRegistryCache = registry.reset
export const ensureTimezoneRegistry = registry.ensure
export function readTimezoneBootCatalogFromRegistry() {
    const entries = registry.read().pools.iana ?? {}, output = Object.create(null)
    for (const [id, compact] of Object.entries(entries)) output[id] = [compact.l, compact.o]
    return output
}
export function sortTimezonesWithPreferred(timezones, preferredId) {
    const index = timezones.findIndex(timezone => timezone.id === preferredId)
    return index <= 0 ? timezones : [timezones[index], ...timezones.slice(0, index), ...timezones.slice(index + 1)]
}
export async function resolveTimezonesFromRegistry({pool = 'iana', timezoneFilterKey = null, preferredTimezoneId = null, sortPreferredFirst = false, locale = null}) {
    const data = await registry.ensure(), entries = data.pools[pool] ?? Object.create(null)
    const names = data.locale_names[locale ?? data.locale]?.[pool] ?? {}
    const ids = timezoneFilterKey && Array.isArray(data.filters[timezoneFilterKey]) ? data.filters[timezoneFilterKey] : Object.keys(entries)
    const timezones = ids.flatMap(id => entries[id] ? [{id, label: names[id] ?? entries[id].l, offset: entries[id].o, region: entries[id].r}] : [])
    return sortPreferredFirst ? sortTimezonesWithPreferred(timezones, preferredTimezoneId) : timezones
}
