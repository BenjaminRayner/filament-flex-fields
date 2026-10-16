/** Compatibility exports. Location owns provider policy, transport and canonical parsing. */
export { GeocodingApiError } from '../location/geocoding/transport.js'
export { searchMapboxPlaces, reverseGeocodeMapbox } from '../location/geocoding/provider.js'
export { parseGeocodeFeature, emptyMapCanonical, emptyAddressCanonical, STREET_ADDRESS_MAPBOX_TYPES,
    hasStreetNumber, isNumberedAddressFeature, hasStreetAddress, isStreetLevelFeature, filterStreetLevelFeatures, resolveMapboxSearchTypes,
    mapboxSearchTypes, hasCoordinates } from '../location/geocoding/state.js'
