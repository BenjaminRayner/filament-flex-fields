const dictionary = () => Object.create(null)
const record = value => value && typeof value === 'object' && !Array.isArray(value)

/** Every locale gets its own names; latest metadata cannot overwrite earlier labels. */
export function mergeCompactPools(payloads, {validKey, normalize, label}) {
    const merged = {locale: 'en', pools: dictionary(), filters: dictionary(), locale_names: dictionary()}
    const putNames = (locale, pool, names) => {
        merged.locale_names[locale] ??= dictionary()
        merged.locale_names[locale][pool] ??= dictionary()
        for (const [key, name] of Object.entries(names)) if (validKey(key) && typeof name === 'string') merged.locale_names[locale][pool][key] = name
    }
    for (const payload of payloads) {
        if (!record(payload?.pools)) continue
        const locale = typeof payload.locale === 'string' && payload.locale ? payload.locale : 'en'
        merged.locale = locale
        for (const [pool, entries] of Object.entries(payload.pools)) {
            if (!record(entries)) continue
            merged.pools[pool] ??= dictionary()
            const names = dictionary()
            for (const [key, entry] of Object.entries(entries)) {
                if (!validKey(key) || !record(entry)) continue
                const normalized = normalize(entry, key)
                if (!normalized) continue
                merged.pools[pool][key] = normalized
                names[key] = label(normalized)
            }
            putNames(locale, pool, names)
        }
        for (const [filter, keys] of Object.entries(record(payload.filters) ? payload.filters : {})) {
            if (Array.isArray(keys)) merged.filters[filter] = [...new Set(keys.filter(key => typeof key === 'string' && validKey(key)))]
        }
        for (const [nameLocale, pools] of Object.entries(record(payload.locale_names) ? payload.locale_names : {})) {
            if (!record(pools)) continue
            for (const [pool, names] of Object.entries(pools)) if (record(names)) putNames(nameLocale, pool, names)
        }
    }
    return merged
}
