import { parseLocalDateTime, showLocalDateTime } from './local-date-time.js'
import { parseCivilDate, formatCivilDate, dateValidation } from './civil-date.js'

export function readSelection(value, config, format = config.storageFormat) {
    const parse = input => config.storesTime ? parseLocalDateTime(input, format, config.locale) ?? parseLocalDateTime(input, 'Y-m-d\\TH:i:s', config.locale) : parseCivilDate(input, format, config.locale, config.mode === 'month' ? { day: 1 } : config.mode === 'year' ? { month: 1, day: 1 } : {})
    if (config.mode !== 'dateRange') return parse(value)
    if (!value) return null
    const range = typeof value === 'string' ? value.split(config.rangeSeparator) : [value.start, value.end]
    if (range.length !== 2) return null
    const start = parse(range[0]), end = parse(range[1])
    return start && end ? { start, end } : null
}

export function showSelection(value, config, format = config.displayFormat) {
    if (!value) return ''
    const show = date => config.storesTime ? showLocalDateTime(date, format, config.locale) : formatCivilDate(date, format, config.locale)
    if (config.mode !== 'dateRange') return show(value)
    if (config.compactRangeLabel && format === config.displayFormat) {
        if (value.start === value.end) return show(value.start)
        const sameYear = value.start.slice(0, 4) === value.end.slice(0, 4), sameMonth = value.start.slice(0, 7) === value.end.slice(0, 7)
        return `${formatCivilDate(value.start, sameYear ? 'M j' : 'M j, Y', config.locale)} – ${formatCivilDate(value.end, sameMonth ? 'j, Y' : 'M j, Y', config.locale)}`
    }
    return `${show(value.start)}${config.rangeSeparator}${show(value.end)}`
}

export function storeSelection(value, config) {
    if (!value) return null
    const store = date => config.storesTime ? showLocalDateTime(date, config.storageFormat) : formatCivilDate(date, config.storageFormat)
    return config.mode === 'dateRange' ? { start: store(value.start), end: store(value.end) } : store(value)
}

export function selectionValidation(value, config) {
    if (!value) return 'invalid'
    const valid = item => dateValidation(item.slice(0, 10), config) || (config.minDateTime && item < config.minDateTime ? 'before_min' : config.maxDateTime && item > config.maxDateTime ? 'after_max' : config.storesTime && config.allowedTimes != null && !config.allowedTimes.includes(item.slice(11)) ? 'unavailable' : null)
    if (config.mode !== 'dateRange') return valid(value)
    return value.end < value.start ? 'range_order' : (!config.allowSameDay && value.start.slice(0, 10) === value.end.slice(0, 10) ? 'same_day_not_allowed' : valid(value.start) || valid(value.end))
}
