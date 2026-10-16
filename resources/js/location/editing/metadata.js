const encoder = new TextEncoder()
export const metadataBytes = value => encoder.encode(JSON.stringify(value)).length
export const validMetadataText = value => typeof value === 'string' && encoder.encode(value).length <= 2048
    && !/[\uD800-\uDBFF](?![\uDC00-\uDFFF])|(?:^|[^\uD800-\uDBFF])[\uDC00-\uDFFF]/u.test(value)

/** PHP FeatureMetadata contract. Reject cycles, prototype keys, functions and excessive allocations. */
export function validMetadata(value) {
    let nodes = 0
    function visit(item, depth) {
        if (depth > 6 || ++nodes > 256) return false
        if (item === null || typeof item === 'boolean') return true
        if (typeof item === 'string') return validMetadataText(item)
        if (typeof item === 'number') return Number.isFinite(item)
        if (!item || typeof item !== 'object' || !Array.isArray(item) && Object.getPrototypeOf(item) !== Object.prototype) return false
        const entries = Object.entries(item)
        if (Array.isArray(item) && (item.length > 100 || entries.length !== item.length || entries.some(([key]) => !/^(0|[1-9]\d*)$/.test(key) || Number(key) >= item.length))) return false
        return entries.length <= 100 && entries.every(([key, value]) =>
            (Array.isArray(item) || /^[a-zA-Z][a-zA-Z0-9_]{0,63}$/.test(key) && !['constructor', 'prototype'].includes(key)) && visit(value, depth + 1))
    }
    return visit(value, 0) && metadataBytes(value) <= 16384
}
