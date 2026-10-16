/** Stable parsed CSS identities; source locations never affect ownership. */
export const canonicalCssNode = value => {
    if (Array.isArray(value)) return value.map(canonicalCssNode)
    if (value && typeof value === 'object') return Object.fromEntries(Object.entries(value)
        .filter(([key]) => key !== 'loc').sort(([a], [b]) => a.localeCompare(b))
        .map(([key, child]) => [key, canonicalCssNode(child)]))
    return value
}

export function cssRuleContext(rule) {
    const { rules, loc, declarations, keyframes, ...header } = rule.value ?? {}
    return { type: rule.type, value: canonicalCssNode(header) }
}
