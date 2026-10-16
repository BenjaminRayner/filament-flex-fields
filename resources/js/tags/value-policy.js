/** Tag identity is independent of display affixes and menu presentation. */
export const tagIdentity = (value, insensitive = false) => insensitive ? String(value).toLowerCase() : String(value)
const bytes = value => new TextEncoder().encode(value).length

export function acceptTag(raw, { selected, options, maximum = null, suggestionsOnly = false, insensitive = false }) {
    const value = String(raw ?? '').trim()
    if (!value || bytes(value) > 65536 || selected.length >= (maximum ?? 10000)) return null
    if (selected.reduce((total, tag) => total + bytes(tag), bytes(value)) > 1048576) return null
    const identity = tagIdentity(value, insensitive)
    if (selected.some(tag => tagIdentity(tag, insensitive) === identity)) return null
    const suggestion = options.find(option => tagIdentity(option.value, insensitive) === identity)
    return suggestionsOnly ? suggestion?.value ?? null : suggestion?.value ?? value
}

export function splitTagPaste(text, keys) {
    const separators = keys.map(key => key === 'Tab' ? '\t' : key === 'Enter' ? '\n' : key)
        .filter(key => typeof key === 'string' && key.length === 1)
    if (!separators.length) return [text]
    const escaped = separators.map(key => key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))
    return String(text).split(new RegExp(escaped.join('|'), 'u'), 10001)
}

/** One bounded linear pass and one publication, even for a large clipboard batch. */
export function acceptTagBatch(candidates, policy) {
    const {selected, options, maximum = null, suggestionsOnly = false, insensitive = false} = policy
    const result = selected.slice()
    const known = new Set(result.map(value => tagIdentity(value, insensitive)))
    const suggestions = new Map(options.map(option => [tagIdentity(option.value, insensitive), option.value]))
    let size = result.reduce((total, value) => total + bytes(value), 0)
    for (const raw of candidates.slice(0, 10000)) {
        if (result.length >= (maximum ?? 10000)) break
        const text = String(raw ?? '').trim(), key = tagIdentity(text, insensitive)
        const value = suggestions.get(key) ?? (suggestionsOnly ? null : text)
        if (!value || known.has(key)) continue
        const length = bytes(value)
        if (length > 65536 || size + length > 1048576) continue
        known.add(key); size += length; result.push(value)
    }
    return result
}
