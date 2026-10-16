/** Strict elapsed-time values, independent of calendar dates and timezones. */
export const MAX_DURATION_SECONDS = 359999999
export function parseDuration(value) {
    if (typeof value !== 'string') return null
    const parts = value.trim().match(/^(\d{1,5}):([0-5][0-9])(?::([0-5][0-9]))?$/)
    return parts ? Number(parts[1]) * 3600 + Number(parts[2]) * 60 + Number(parts[3] || 0) : null
}
export function formatDuration(seconds, withSeconds = false) {
    if (!Number.isInteger(seconds) || seconds < 0 || seconds > MAX_DURATION_SECONDS) return ''
    const pad = number => String(number).padStart(2, '0')
    const value = `${pad(Math.floor(seconds / 3600))}:${pad(Math.floor(seconds % 3600 / 60))}`
    return withSeconds ? `${value}:${pad(seconds % 60)}` : value
}
