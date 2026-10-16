/** Bounded ASCII policy matches PHP CodePolicy; incomplete slots remain local. */
export function createCodeModel(length, characters) {
    if (!Number.isInteger(length) || length < 1 || length > 16 || !['numeric', 'alphanumeric'].includes(characters)) {
        throw new Error('Invalid verification code configuration.')
    }
    const numeric = characters === 'numeric'
    const normalize = value => typeof value === 'string'
        ? value.replace(numeric ? /[^0-9]/g : /[^A-Za-z0-9]/g, '').slice(0, length).toUpperCase() : ''
    const cells = value => {
        const text = normalize(value)
        return Array.from({ length }, (_, index) => text[index] ?? '')
    }
    return {
        length, normalize, cells,
        write(current, index, value) {
            const text = normalize(value)
            const next = [...current]
            if (!text) { next[index] = ''; return { cells: next, focus: index } }
            const start = text.length === length ? 0 : index
            for (let offset = 0; offset < text.length && start + offset < length; offset++) next[start + offset] = text[offset]
            return { cells: next, focus: Math.min(length - 1, start + text.length) }
        },
        paste(value) {
            const text = normalize(value)
            if (!text) return null
            return { cells: cells(text), focus: Math.min(length - 1, text.length) }
        },
        complete(values) { return values.length === length && values.every(value => value.length === 1) },
    }
}
