import { resolveControlMetadata } from '../../runtime/alpine/control-configurations.js'
const documents = new WeakMap()
const MAX_CATALOGS = 64

/** Freeze parsed JSON iteratively, including nested groups and rich metadata. */
function freezeCatalog(options) {
    const pending = [options]
    while (pending.length) {
        const value = pending.pop()
        for (const child of Object.values(value)) {
            if (child !== null && typeof child === 'object') pending.push(child)
        }
        Object.freeze(value)
    }
    return options
}

/** Each instance owns a transport; identical immutable payloads share one parsed catalog. */
export function readSelectCatalog(key, root = typeof document === 'undefined' ? null : document) {
    if (!key || !root) return []
    let cache = documents.get(root)
    if (!cache) { cache = new Map(); documents.set(root, cache) }
    let options = cache.get(key)
    if (!options) {
        const reference = [...root.querySelectorAll('[data-fff-select-catalog-ref]')]
            .find(candidate => candidate.dataset.fffSelectCatalogRef === key)
        if (reference) {
            const metadata = resolveControlMetadata(reference.getAttribute('data-fff-control-configuration-id'), 'select-catalog', reference)
            if (metadata.catalogKey !== key || !Array.isArray(metadata.options)) throw Error('Mismatched Select catalog metadata.')
            options = metadata.options
        } else {
            const node = [...root.querySelectorAll('[data-fff-select-catalog]')]
                .find(candidate => candidate.dataset.fffSelectCatalog === key)
            if (!node) throw new Error(`Select catalog ${key} is missing from its rendered document.`)
            const text = node.content?.textContent ?? node.textContent ?? ''
            if (text.length > 4194304) throw new Error('Select catalog exceeds its transport budget.')
            options = JSON.parse(text)
            if (!Array.isArray(options)) throw new Error('Select catalog must contain option records.')
        }
        if (!Object.isFrozen(options)) freezeCatalog(options)
        if (cache.size >= MAX_CATALOGS) cache.delete(cache.keys().next().value)
        cache.set(key, options)
    }
    return options.slice()
}

export function prepareCatalogSelectConfiguration(config, root, detectTimezone = () => Intl.DateTimeFormat().resolvedOptions().timeZone) {
    if (!config.optionsRegistryKey) return config
    let options = readSelectCatalog(config.optionsRegistryKey, root)
    let detected = null
    if (config.browserTimezoneDefault || config.browserTimezoneSortFirst) {
        try { detected = detectTimezone() } catch { /* Intl is optional. */ }
        if (!options.some(option => option.value === detected && !option.isDisabled)) detected = null
    }
    if (detected && config.browserTimezoneSortFirst) {
        const index = options.findIndex(option => option.value === detected)
        options = [options[index], ...options.slice(0, index), ...options.slice(index + 1)]
    }
    const state = config.state ?? config.initialState
    const initialState = config.browserTimezoneDefault && !config.disabled && (state == null || state === '') && detected
        ? detected : config.initialState
    return {...config, options, initialState, initialOptionLabel: initialState !== config.initialState
        ? options.find(option => option.value === initialState)?.triggerLabel ?? null : config.initialOptionLabel}
}
