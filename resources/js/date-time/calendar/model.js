import { createCalendar, GregorianCalendar, toCalendar, parseDate, today, startOfMonth, endOfMonth, getDayOfWeek } from '@internationalized/date'

const gregorian = new GregorianCalendar()
export const canonicalDate = date => toCalendar(date, gregorian).toString()

export function createCalendarModel(config, initial) {
    const calendar = createCalendar(config.calendarIdentifier || 'gregory')
    const current = today(config.timeZone)
    const convert = value => toCalendar(parseDate(value), calendar)
    let base = initial || canonicalDate(current)
    if (config.minValue && base < config.minValue) base = config.minValue
    if (config.maxValue && base > config.maxValue) base = config.maxValue
    const formatter = options => new Intl.DateTimeFormat(config.locale.replaceAll('_', '-'), { ...options, calendar: calendar.identifier, timeZone: 'UTC' })
    const monthName = formatter({ month: 'long' }), yearName = formatter({ year: 'numeric' }), fullName = formatter({ dateStyle: 'full' })
    const weekdayName = formatter({ weekday: 'short' }), dayName = new Intl.NumberFormat(config.locale.replaceAll('_', '-'), { useGrouping: false })
    return {
        convert, today: canonicalDate(current), initialMonth: startOfMonth(convert(base)),
        monthLabel: date => monthName.format(date.toDate('UTC')),
        yearLabel: date => yearName.format(date.toDate('UTC')),
        dateLabel: date => fullName.format(date.toDate('UTC')),
        dayLabel: date => dayName.format(date.day),
        monthAllowed(date) {
            const first = canonicalDate(startOfMonth(date)), last = canonicalDate(endOfMonth(date))
            return first >= '0001-01-01' && last <= '9999-12-31'
                && (!config.minValue || last >= config.minValue) && (!config.maxValue || first <= config.maxValue)
        },
        weeks(month) {
            const first = startOfMonth(month)
            const offset = (getDayOfWeek(first, 'en-US') - config.firstDayOfWeek + 7) % 7
            const start = first.subtract({ days: offset })
            const count = config.fixedWeeks ? 6 : Math.ceil((offset + month.calendar.getDaysInMonth(month)) / 7)
            const weeks = Array.from({ length: count }, (_, week) => Array.from({ length: 7 }, (_, day) => {
                const date = start.add({ days: week * 7 + day })
                return { date, value: canonicalDate(date), outside: date.month !== month.month || date.year !== month.year || date.era !== month.era }
            }))
            return { weeks, weekdays: weeks[0].map(({ date }) => weekdayName.format(date.toDate('UTC'))), start: weeks[0][0].date.era === 'BC' ? '0001-01-01' : weeks[0][0].value, end: weeks.at(-1).at(-1).date.year > 9999 ? '9999-12-31' : weeks.at(-1).at(-1).value }
        },
    }
}
