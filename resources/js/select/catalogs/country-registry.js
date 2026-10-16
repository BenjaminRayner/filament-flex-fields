import { createDocumentRegistry } from './document-registry.js'
import { mergeCompactPools } from './compact-pool-merge.js'
const dictionary = () => Object.create(null)

export function mergeCountryRegistryPayloads(payloads) {
    return mergeCompactPools(payloads, {
        validKey: code => /^[A-Z]{2}$/.test(code),
        normalize: (country, code) => country.c === code && typeof country.n === 'string' && typeof country.f === 'string'
            ? {c: code, n: country.n, d: typeof country.d === 'string' ? country.d : '', f: country.f} : null,
        label: country => country.n,
    })
}
const registry = createDocumentRegistry({selector: '#fff-country-registry-data', merge: mergeCountryRegistryPayloads})
export const resetCountryRegistryCache = registry.reset
export const ensureCountryRegistry = registry.ensure

export function sortCountriesWithPreferred(countries, preferredCode) {
    const preferred = String(preferredCode ?? '').toUpperCase()
    const index = countries.findIndex(country => country.code === preferred)
    return index <= 0 ? countries : [countries[index], ...countries.slice(0, index), ...countries.slice(index + 1)]
}

export async function resolveCountriesFromRegistry({pool, allowedCountryCodes = null, countryFilterKey = null,
    preferredCountryCode = null, sortPreferredFirst = false, locale = null}) {
    const registry = await ensureCountryRegistry()
    const data = registry.pools[pool] ?? dictionary()
    const names = registry.locale_names[locale ?? registry.locale]?.[pool] ?? dictionary()
    const codes = countryFilterKey && Array.isArray(registry.filters[countryFilterKey]) ? registry.filters[countryFilterKey]
        : Array.isArray(allowedCountryCodes) ? allowedCountryCodes : Object.keys(data)
    let countries = [...new Set(codes)].flatMap(code => {
        const country = data[code]
        return country ? [{code: country.c, name: names[code] ?? country.n, dial_code: country.d || null, flag_url: country.f}] : []
    })
    return sortPreferredFirst ? sortCountriesWithPreferred(countries, preferredCountryCode) : countries
}

