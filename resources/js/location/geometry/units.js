export const UNIT_METERS = { meters: 1, kilometers: 1000, feet: .3048, miles: 1609.344, nautical: 1852 }
export const UNIT_LABELS = { meters: 'm', kilometers: 'km', feet: 'ft', miles: 'mi', nautical: 'nmi' }
export function displayDimensions(dimensions, unit = 'meters') {
    if (!dimensions) return null
    return Object.fromEntries(Object.entries(dimensions).map(([key, meters]) => [key, Math.round(meters / UNIT_METERS[unit] * 1000) / 1000]))
}
