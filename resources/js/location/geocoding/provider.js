import { GeocodingApiError, requestGeocoding } from './transport.js'
import { resolveMapboxSearchTypes, applyAddressPolicy, parseGeocodeFeature, hasCoordinates } from './state.js'

function directUrl(coordinateOrQuery, configuration, { reverse = false } = {}) {
    if (!configuration.accessToken) throw new GeocodingApiError('Mapbox access token is missing.')
    const url = new URL(`https://api.mapbox.com/geocoding/v5/mapbox.places/${reverse ? coordinateOrQuery : encodeURIComponent(coordinateOrQuery)}.json`)
    url.searchParams.set('access_token', configuration.accessToken)
    url.searchParams.set('language', configuration.language ?? 'en')
    if (configuration.types) url.searchParams.set('types', configuration.types)
    if (configuration.countries?.length) url.searchParams.set('country', configuration.countries.join(',').toLowerCase())
    if (!reverse) {
        url.searchParams.set('limit', String(configuration.limit))
        url.searchParams.set('autocomplete', configuration.autocomplete === false ? 'false' : 'true')
        if (configuration.bounds) url.searchParams.set('bbox', configuration.bounds.join(','))
        if (configuration.proximity) url.searchParams.set('proximity', configuration.proximity.join(','))
    }
    return url
}

function resolvePolicy(options) {
    return { ...options, language: options.language ?? 'en',
        types: resolveMapboxSearchTypes(options), limit: options.limit ?? 6 }
}

function featuresFrom(payload, limit) {
    if (!Array.isArray(payload.features) || payload.features.length > limit) throw new GeocodingApiError('Invalid geocoding feature collection.')
    return payload.features
}

export async function searchMapboxPlaces(options) {
    const configuration = resolvePolicy(options)
    const query = String(options.query ?? '').trim()
    if (!query || options.signal?.aborted || Array.isArray(options.countries) && options.countries.length === 0) return []
    if (query.length > 256 || !Number.isInteger(configuration.limit) || configuration.limit < 1 || configuration.limit > 10) throw new GeocodingApiError('Invalid geocoding search policy.')
    const payload = configuration.geocodeSearchUrl
        ? await requestGeocoding(configuration.geocodeSearchUrl, { signal: options.signal, body: {
            query, language: configuration.language, limit: configuration.limit,
            bbox: configuration.bounds ?? null, proximity: configuration.proximity ?? null,
            autocomplete: configuration.autocomplete !== false, types: configuration.types,
            countries: configuration.countries ?? null, street_addresses_only: configuration.streetAddressesOnly ?? false,
            require_street_number: configuration.requireStreetNumber ?? false,
        } })
        : await requestGeocoding(directUrl(query, configuration), { signal: options.signal })
    const features = featuresFrom(payload, configuration.limit)
    return applyAddressPolicy(features, configuration)
}

export async function reverseGeocodeMapbox(options) {
    if (options.signal?.aborted || Array.isArray(options.countries) && options.countries.length === 0) return null
    if (!hasCoordinates(options)) throw new GeocodingApiError('Invalid reverse-geocoding coordinates.')
    const configuration = resolvePolicy(options)
    const payload = configuration.geocodeReverseUrl
        ? await requestGeocoding(configuration.geocodeReverseUrl, { signal: options.signal, body: {
            lng: Number(options.lng), lat: Number(options.lat), language: configuration.language,
            types: configuration.types, countries: configuration.countries ?? null,
            street_addresses_only: configuration.streetAddressesOnly ?? false,
            require_street_number: configuration.requireStreetNumber ?? false,
        } })
        : await requestGeocoding(directUrl(`${Number(options.lng)},${Number(options.lat)}`, configuration, { reverse: true }), { signal: options.signal })
    if (configuration.geocodeReverseUrl) return payload.feature && applyAddressPolicy([payload.feature], configuration).length ? parseGeocodeFeature(payload.feature) : null
    const features = featuresFrom(payload, 10)
    const feature = applyAddressPolicy(features, configuration)[0]
    return feature ? parseGeocodeFeature(feature) : null
}
