import { readSelection } from '../domain/selection.js'
import { civilDate, formatCivilDate } from '../domain/civil-date.js'

/** Keep individually valid endpoints when a range is still incomplete. */
export function readSegmentSelection(value, config) {
    const complete = readSelection(value, config)
    if (complete || config.mode !== 'dateRange' || !value || typeof value !== 'object') return complete
    const single = { ...config, mode: 'date' }
    return { start: readSelection(value.start, single), end: readSelection(value.end, single) }
}

/** Numeric segment state is independent of locale text and transport format. */
export function createDateSegmentModel(config, canonical) {
    const pattern = config.segmentPattern
    let bases = [], groups = []
    const two = value => String(value).padStart(2, '0')
    function hydrate(value) {
        bases = config.mode === 'dateRange' ? [value?.start, value?.end] : [value]
        groups = bases.map(date => pattern.map(segment => {
            if (!date || segment.type === 'literal') return null
            const [year, month, day] = date.slice(0, 10).split('-').map(Number)
            const [hour = 0, minute = 0, second = 0] = (date.slice(11) || '00:00:00').split(':').map(Number)
            return { year: segment.token === 'y' ? year % 100 : year, month, day,
                hour: 'hg'.includes(segment.token) ? hour % 12 || 12 : hour, minute, second, period: hour >= 12 ? 1 : 0 }[segment.type]
        }))
    }
    hydrate(canonical)
    function fields(group) {
        const result = {}
        pattern.forEach((segment, index) => { if (segment.type !== 'literal') result[segment.type] = groups[group][index] })
        return result
    }
    function limit(group, index) {
        const segment = pattern[index]
        if (segment.type !== 'day') return segment.max
        const values = fields(group)
        const year = values.year || 2000, month = values.month || 1
        for (let day = 31; day >= 28; day--) if (civilDate(year, month, day)) return day
        return 31
    }
    function set(group, index, value, clamp = true) {
        groups[group][index] = value
        // Changing month/year preserves a meaningful date, including leap days.
        if (clamp && ['month', 'year'].includes(pattern[index].type)) {
            pattern.forEach((segment, dayIndex) => {
                if (segment.type === 'day' && groups[group][dayIndex] > limit(group, dayIndex)) groups[group][dayIndex] = limit(group, dayIndex)
            })
        }
    }
    function selection() {
        const dates = groups.map((group, groupIndex) => {
            const values = fields(groupIndex)
            if (Object.values(values).some(value => value === null)) return null
            let year = values.year ?? Number(bases[groupIndex]?.slice(0, 4) || new Date().getFullYear())
            if (pattern.some(segment => segment.token === 'y')) year += year < 70 ? 2000 : 1900
            const date = civilDate(year, values.month ?? 1, values.day ?? 1)
            if (!date || !config.storesTime) return date
            const defaults = (bases[groupIndex]?.slice(11) || config.defaultTime || '00:00:00').split(':').map(Number)
            let hour = values.hour ?? defaults[0]
            if (pattern.some(segment => 'hg'.includes(segment.token) && segment.type === 'hour')) hour = hour % 12 + (values.period === 1 ? 12 : 0)
            return `${date}T${two(hour)}:${two(values.minute ?? defaults[1] ?? 0)}:${two(values.second ?? defaults[2] ?? 0)}`
        })
        return dates.every(Boolean) ? config.mode === 'dateRange' ? { start: dates[0], end: dates[1] } : dates[0] : null
    }
    function text(group, index) {
        const segment = pattern[index], value = groups[group][index]
        if (segment.type === 'literal') return segment.token
        if (value === null) return segment.placeholder
        if (segment.type === 'month' && ['M', 'F'].includes(segment.token)) return formatCivilDate(`2000-${two(value)}-01`, segment.token, config.locale)
        if (segment.type === 'period') return segment.token === 'a' ? (value ? 'pm' : 'am') : (value ? 'PM' : 'AM')
        if (['Y', 'y', 'm', 'd', 'H', 'h', 'i', 's'].includes(segment.token)) return String(value).padStart(segment.token === 'Y' ? 4 : 2, '0')
        return String(value)
    }
    return { pattern, hydrate, set, selection, limit, text,
        get: (group, index) => groups[group][index],
        empty: () => groups.every(group => group.every(value => value === null)),
        raw: () => groups.map((group, groupIndex) => pattern.map((_, index) => text(groupIndex, index)).join('')).join(config.rangeSeparator),
    }
}
