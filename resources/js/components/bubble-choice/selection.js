export function selectedKeys(value, options) {
    if (!Array.isArray(value) || value.length > 4096) return []
    const allowed = new Set(options.filter(option => !option.disabled).map(option => option.value))
    return [...new Set(value.filter(key => typeof key === 'string' || Number.isSafeInteger(key))
        .map(String).filter(key => allowed.has(key)))]
}

export function toggleKey(value, key, options, maximum, disabled = false) {
    const state = selectedKeys(value, options)
    const option = options.find(option => option.value === key)
    if (disabled || !option || option.disabled) return state
    if (state.includes(key)) return state.filter(value => value !== key)
    if (maximum !== null && maximum !== undefined && state.length >= maximum) return state
    return [...state, key]
}
