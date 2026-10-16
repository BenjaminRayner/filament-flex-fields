/** Identities remain opaque strings; malformed state is never coerced into a choice. */
export function choiceIdentity(value) {
    return typeof value === 'string' || Number.isSafeInteger(value) ? String(value) : null
}

export function createChoiceSelection({ options = [], multiple = false, maxSelections = null }) {
    const catalog = new Map(options.map(option => [String(option.value), option]))
    const limit = Number.isInteger(maxSelections) && maxSelections >= 0 ? maxSelections : null
    const selected = state => new Set((multiple ? (Array.isArray(state) ? state : []) : [state])
        .map(choiceIdentity).filter(value => value !== null && catalog.has(value)))
    return {
        selected,
        canChange(value, state, disabled = false, current = selected(state)) {
            const key = choiceIdentity(value), option = catalog.get(key)
            if (disabled || !option || option.disabled) return false
            return !multiple || current.has(key) || limit === null || current.size < limit
        },
        change(value, state, disabled = false) {
            if (!this.canChange(value, state, disabled)) return state
            const key = choiceIdentity(value)
            if (!multiple) return key
            const current = selected(state)
            if (current.has(key)) current.delete(key)
            else current.add(key)
            return [...current]
        },
    }
}
