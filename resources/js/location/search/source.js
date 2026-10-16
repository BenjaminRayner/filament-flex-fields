import { isNumberedAddressFeature } from '../geocoding/state.js'
import { searchMapboxPlaces } from '../../support/mapbox-geocoding.js'

/** One geocoder per rendered Select owner. No global addresses, token cache or DOM. */
export function createLocationOptionSource(configuration, provider = searchMapboxPlaces) {
    if (!Number.isInteger(configuration.minSearchLength) || configuration.minSearchLength < 0 || configuration.minSearchLength > 256
        || !Number.isInteger(configuration.geocoder?.limit) || configuration.geocoder.limit < 1 || configuration.geocoder.limit > 10
        || typeof provider !== 'function') throw new TypeError('Invalid location search policy.')
    const cache = new Map()
    const records = new Map()
    let cacheBytes = 0
    let disposed = false
    const maximumBytes = 1048576
    function remember(entries) {
        records.clear()
        for (const entry of entries) {
            records.set(entry.value, entry)
        }
    }
    return {
        record(value) { return records.get(String(value)) ?? null },
        dispose() { disposed = true; cache.clear(); records.clear(); cacheBytes = 0 },
        async search({query, signal}) {
            query = String(query ?? '').trim()
            if (disposed || signal?.aborted || !query || query.length < configuration.minSearchLength) return []
            if (query.length > 256) throw new RangeError(configuration.labels.queryTooLong)
            const cached = cache.get(query)
            if (cached && Date.now() - cached.at < 60000) {
                cache.delete(query); cache.set(query, cached); remember(cached.entries)
                return cached.entries.map(({value,label}) => ({value,label}))
            }
            const features = await provider({...configuration.geocoder, query, signal})
            if (disposed || signal?.aborted) return []
            if (!Array.isArray(features) || features.length > configuration.geocoder.limit) throw new TypeError('Invalid geocoder response.')
            const entries = [], seen = new Set()
            for (const feature of features) {
                if (configuration.geocoder.requireStreetNumber && !isNumberedAddressFeature(feature)) continue
                if (!feature || typeof feature.id !== 'string' || !feature.id || feature.id.length > 256
                    || typeof feature.place_name !== 'string' || feature.place_name.length > 2048 || seen.has(feature.id)) continue
                seen.add(feature.id)
                entries.push({value:feature.id, label:feature.place_name, feature})
            }
            const bytes = new TextEncoder().encode(JSON.stringify(entries)).length
            if (bytes > maximumBytes) throw new RangeError('Geocoder response exceeds its byte budget.')
            if (cached) { cacheBytes -= cached.bytes; cache.delete(query) }
            cache.set(query, {entries, bytes, at:Date.now()}); cacheBytes += bytes
            while (cache.size > 16 || cacheBytes > maximumBytes) {
                const key = cache.keys().next().value
                cacheBytes -= cache.get(key).bytes; cache.delete(key)
            }
            remember(entries)
            return entries.map(({value,label}) => ({value,label}))
        },
    }
}
