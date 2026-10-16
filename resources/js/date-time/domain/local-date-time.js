import { parseCivilDate, formatCivilDate } from './civil-date.js'
const pad = number => String(number).padStart(2, '0')
const escape = text => text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
export function splitDateTimeFormat(format) {
    let literal = false
    for (let index = 0; index < format.length; index++) {
        if (literal) { literal = false; continue }
        if (format[index] === '\\') { literal = true; continue }
        if ('HgGh'.includes(format[index])) return [format.slice(0, index).trimEnd(), format.slice(index)]
    }
    return [format, '']
}
export function parseClock(value, format = 'H:i:s') {
    if (typeof value !== 'string') return null
    let pattern = '', escaped = false
    const parts = [], tokens = { H: '(\\d{1,2})', G: '(\\d{1,2})', h: '(\\d{1,2})', g: '(\\d{1,2})', i: '(\\d{1,2})', s: '(\\d{1,2})', A: '(AM|PM)', a: '(am|pm)' }
    for (const token of format) {
        if (escaped) { pattern += escape(token); escaped = false }
        else if (token === '\\') escaped = true
        else if (tokens[token]) { pattern += tokens[token]; parts.push(token) }
        else pattern += escape(token)
    }
    const match = value.trim().match(new RegExp(`^${pattern}$`)); if (!match) return null
    const fields = {}; parts.forEach((token, index) => { fields[token] = match[index + 1] })
    let hour = Number(fields.H ?? fields.G ?? fields.h ?? fields.g), minute = Number(fields.i ?? 0), second = Number(fields.s ?? 0)
    if (fields.h !== undefined || fields.g !== undefined) { if (hour < 1 || hour > 12 || !(fields.A || fields.a)) return null; hour = hour % 12 + (/pm/i.test(fields.A || fields.a) ? 12 : 0) }
    if (![hour, minute, second].every(Number.isInteger) || hour < 0 || hour > 23 || minute < 0 || minute > 59 || second < 0 || second > 59) return null
    return `${pad(hour)}:${pad(minute)}:${pad(second)}`
}
export function showClock(time, format = 'H:i') {
    const canonical = parseClock(time); if (!canonical) return ''
    const [hour, minute, second] = canonical.split(':').map(Number)
    const parts = { H: pad(hour), G: String(hour), h: pad(hour % 12 || 12), g: String(hour % 12 || 12), i: pad(minute), s: pad(second), A: hour >= 12 ? 'PM' : 'AM', a: hour >= 12 ? 'pm' : 'am' }
    let output = '', escaped = false
    for (const token of format) { if (escaped) { output += token; escaped = false } else if (token === '\\') escaped = true; else output += parts[token] ?? token }
    return output
}
export function parseLocalDateTime(value, format, locale) {
    if (typeof value !== 'string') return null
    const [dateFormat, timeFormat] = splitDateTimeFormat(format)
    if (!timeFormat) { const date = parseCivilDate(value, dateFormat, locale); return date ? `${date}T00:00:00` : null }
    for (let index = value.length - 1; index >= 0; index--) {
        const time = parseClock(value.slice(index), timeFormat)
        if (!time) continue
        const date = parseCivilDate(value.slice(0, index).trimEnd(), dateFormat, locale)
        if (date) return `${date}T${time}`
    }
    return null
}
export function showLocalDateTime(value, format, locale) {
    const canonical = parseLocalDateTime(value, 'Y-m-d\\TH:i:s', 'en-US'); if (!canonical) return ''
    const [dateFormat, timeFormat] = splitDateTimeFormat(format)
    const date = formatCivilDate(canonical.slice(0, 10), dateFormat, locale)
    return `${date}${dateFormat.endsWith('\\T') ? '' : timeFormat ? ' ' : ''}${showClock(canonical.slice(11), timeFormat)}`
}
export function datePart(value, range = false) {
    if (!value) return null
    return range ? { start: value.start.slice(0, 10), end: value.end.slice(0, 10) } : value.slice(0, 10)
}
export function fitTime(date, time, config) {
    if (config.allowedTimes != null) {
        const choices = config.allowedTimes.filter(value => (!config.minDateTime || `${date}T${value}` >= config.minDateTime) && (!config.maxDateTime || `${date}T${value}` <= config.maxDateTime))
        if (choices.includes(time)) return time
        return choices.find(value => value >= time) ?? choices.at(-1) ?? time
    }
    const candidate = `${date}T${time}`
    if (config.minDateTime && candidate < config.minDateTime && date === config.minDateTime.slice(0, 10)) return config.minDateTime.slice(11)
    if (config.maxDateTime && candidate > config.maxDateTime && date === config.maxDateTime.slice(0, 10)) return config.maxDateTime.slice(11)
    return time
}
