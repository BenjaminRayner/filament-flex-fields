import { createDocumentRegistry } from './document-registry.js'
import { mergeCompactPools } from './compact-pool-merge.js'
const policy = {
    validKey: key => /^[A-Z]{3}$/.test(key),
    normalize: entry => typeof entry.n === 'string' ? {
        n: entry.n, s: typeof entry.s === 'string' ? entry.s : '',
        d: Number.isInteger(Number(entry.d ?? 2)) && Number(entry.d ?? 2) >= 0 && Number(entry.d ?? 2) <= 20 ? Number(entry.d ?? 2) : 2,
        l: typeof entry.l === 'string' ? entry.l : 'en',
    } : null,
    label: entry => entry.n,
}
const registry = createDocumentRegistry({selector: '#fff-currency-registry-data', merge: payloads => mergeCompactPools(payloads, policy)})
export const resetCurrencyRegistryCache = registry.reset
export const ensureCurrencyRegistry = registry.ensure
export async function resolveCurrenciesFromRegistry({pool = 'iso', currencyFilterKey = null}) {
    const data = await registry.ensure(), entries = data.pools[pool] ?? Object.create(null)
    const codes = currencyFilterKey && Array.isArray(data.filters[currencyFilterKey]) ? data.filters[currencyFilterKey] : Object.keys(entries)
    return codes.flatMap(code => entries[code] ? [{code, symbol: entries[code].s, name: entries[code].n, decimals: entries[code].d, locale: entries[code].l}] : [])
}
