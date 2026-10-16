const pad = (value, length = 2) => String(value).padStart(length, '0')
const DATE_TOKENS = { Y: '(\\d{4})', y: '(\\d{2})', m: '(\\d{1,2})', n: '(\\d{1,2})', d: '(\\d{1,2})', j: '(\\d{1,2})' }
const monthCache = new Map()
function monthNames(locale, style) {
    const key = `${locale}/${style}`
    if (!monthCache.has(key)) {
        const formatter = new Intl.DateTimeFormat(locale.replaceAll('_', '-'), { month: style, calendar: 'gregory', timeZone: 'UTC' })
        monthCache.set(key, Array.from({ length: 12 }, (_, index) => formatter.format(new Date(Date.UTC(2000, index, 1)))))
        if (monthCache.size > 24) monthCache.delete(monthCache.keys().next().value)
    }
    return monthCache.get(key)
}
const escape = (value) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')

/** Civil dates never use the host timezone or permissive Date.parse(). */
export function civilDate(year, month, day) {
    if (![year, month, day].every(Number.isInteger) || year < 1 || year > 9999 || month < 1 || month > 12 || day < 1 || day > 31) return null
    const date = new Date(0)
    date.setUTCFullYear(year, month - 1, day)
    date.setUTCHours(0, 0, 0, 0)
    if (date.getUTCFullYear() !== year || date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day) return null
    return `${pad(year, 4)}-${pad(month)}-${pad(day)}`
}

export function parseCivilDate(value, format = 'Y-m-d', locale = 'en-US', defaults = {}) {
    if (typeof value !== 'string' || value.trim() === '') return null
    const tokens = []
    let pattern = '^', literal = false
    for (const part of format) {
        if (literal) { pattern += escape(part); literal = false; continue }
        if (part === '\\') { literal = true; continue }
        if (part === 'M' || part === 'F') { pattern += `(${monthNames(locale, part === 'M' ? 'short' : 'long').map(escape).join('|')})`; tokens.push(part) }
        else if (DATE_TOKENS[part]) { pattern += DATE_TOKENS[part]; tokens.push(part) }
        else pattern += escape(part)
    }
    const match = value.trim().match(new RegExp(pattern + '$'))
    if (!match) return null
    const fields = {}
    tokens.forEach((token, index) => { fields[token] = token === 'M' || token === 'F' ? monthNames(locale, token === 'M' ? 'short' : 'long').indexOf(match[index + 1]) + 1 : Number(match[index + 1]) })
    let year = fields.Y
    if (year === undefined && fields.y !== undefined) year = fields.y < 70 ? fields.y + 2000 : fields.y + 1900
    return civilDate(year, fields.m ?? fields.n ?? fields.M ?? fields.F ?? defaults.month, fields.d ?? fields.j ?? defaults.day)
}

export function formatCivilDate(value, format = 'Y-m-d', locale = 'en-US') {
    const canonical = parseCivilDate(value)
    if (!canonical) return ''
    const [year, month, day] = canonical.split('-').map(Number)
    const tokens = { Y: pad(year, 4), y: pad(year % 100), m: pad(month), n: String(month), d: pad(day), j: String(day), M: monthNames(locale, 'short')[month - 1], F: monthNames(locale, 'long')[month - 1] }
    let output = '', literal = false
    for (const part of format) {
        if (literal) { output += part; literal = false; continue }
        if (part === '\\') { literal = true; continue }
        output += tokens[part] ?? part
    }
    return output
}

export function dateValidation(value, config) {
    if (!value) return null
    if (config.minValue && value < config.minValue) return 'before_min'
    if (config.maxValue && value > config.maxValue) return 'after_max'
    if (config.storesTime && config.allowedTimes != null && !config.allowedTimes.some(time => (!config.minDateTime || `${value}T${time}` >= config.minDateTime) && (!config.maxDateTime || `${value}T${time}` <= config.maxDateTime))) return 'unavailable'
    const date = new Date(`${value}T00:00:00Z`)
    if (config.disabledWeekdays.includes(date.getUTCDay()) || config.unavailableDates.includes(value)) return 'unavailable'
    return null
}
