const MAP_KEYS = ['lat', 'lng', 'street', 'city', 'region', 'postcode', 'country', 'country_name', 'place_name']

export function emptyMapCanonical() { return Object.fromEntries(MAP_KEYS.map(key => [key, null])) }
export function emptyAddressCanonical() { return Object.fromEntries(MAP_KEYS.slice(2).map(key => [key, null])) }

export function hasCoordinates(state) {
    const coordinate = value => (typeof value === 'number' || typeof value === 'string' && value.trim() !== '') && Number.isFinite(Number(value))
    return coordinate(state?.lat) && coordinate(state?.lng) && Math.abs(Number(state.lat)) <= 90 && Math.abs(Number(state.lng)) <= 180
}

export function hasStreetAddress(state) { return String(state?.street ?? '').trim() !== '' }

const text = value => typeof value === 'string' ? value.slice(0, 2048) : ''

/** Provider features are untrusted data, never HTML or a second form-state engine. */
export function parseGeocodeFeature(feature) {
    const value = emptyMapCanonical()
    const coordinates = feature?.center ?? feature?.geometry?.coordinates
    if (Array.isArray(coordinates) && coordinates.length >= 2
        && hasCoordinates({ lat: coordinates[1], lng: coordinates[0] })) {
        value.lat = Number(coordinates[1])
        value.lng = Number(coordinates[0])
    }
    const types = Array.isArray(feature?.place_type) ? feature.place_type : []
    const title = text(feature?.text)
    const number = typeof feature?.address === 'number' ? String(feature.address) : text(feature?.address)
    if (number) value.street = [number, title].filter(Boolean).join(' ')
    else if (types.includes('address') || types.includes('poi')) value.street = title || null

    for (const entry of Array.isArray(feature?.context) ? feature.context.slice(0, 32) : []) {
        const type = text(entry?.id).split('.')[0]
        const label = text(entry?.text) || null
        if (type === 'place' || type === 'locality' && !value.city) value.city = label
        else if (type === 'region') value.region = label
        else if (type === 'postcode') value.postcode = label
        else if (type === 'country') {
            value.country_name = label
            const code = text(entry?.short_code).toUpperCase()
            value.country = /^[A-Z]{2}$/.test(code) ? code : null
        }
    }
    if (!value.city && types.includes('place')) value.city = title || null
    if (types.includes('region')) value.region = title || value.region
    if (types.includes('postcode')) value.postcode = title || value.postcode
    if (types.includes('country')) {
        value.country_name = title || null
        const code = text(feature?.properties?.short_code ?? feature?.short_code).toUpperCase()
        value.country = /^[A-Z]{2}$/.test(code) ? code : null
    }
    value.place_name = text(feature?.place_name) || null
    return value
}

export const STREET_ADDRESS_MAPBOX_TYPES = 'address'
export function isStreetLevelFeature(feature) {
    return Array.isArray(feature?.place_type) && feature.place_type.includes('address') && hasStreetAddress(parseGeocodeFeature(feature))
}
export function filterStreetLevelFeatures(features) { return Array.isArray(features) ? features.filter(isStreetLevelFeature) : [] }
export function resolveMapboxSearchTypes({ types = null, streetAddressesOnly = false, requireStreetNumber = false } = {}) {
    if (streetAddressesOnly || requireStreetNumber) return STREET_ADDRESS_MAPBOX_TYPES
    const entries = Array.isArray(types) ? types : typeof types === 'string' ? types.split(',') : []
    const normalized = [...new Set(entries.filter(value => typeof value === 'string').map(value => value.trim()).filter(Boolean))]
    return normalized.length ? normalized.join(',') : null
}
export function mapboxSearchTypes(streetAddressesOnly) { return resolveMapboxSearchTypes({ streetAddressesOnly }) }

export function hasStreetNumber(state) { return /^\d+[\p{L}\d/\-]*\s+\S/u.test(String(state?.street ?? '')) }
export function isNumberedAddressFeature(feature) {
    return isStreetLevelFeature(feature) && hasStreetNumber(parseGeocodeFeature(feature))
}
export function applyAddressPolicy(features, configuration) {
    if (configuration.requireStreetNumber) return features.filter(isNumberedAddressFeature)
    return configuration.streetAddressesOnly ? filterStreetLevelFeatures(features) : features
}
