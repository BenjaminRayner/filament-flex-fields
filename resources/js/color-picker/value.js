const clamp = (n, a, b) => Math.min(b, Math.max(a, n))
export function rgbToHsv(r, g, b, a = 1) {
    r /= 255; g /= 255; b /= 255
    const max = Math.max(r, g, b), min = Math.min(r, g, b), delta = max - min
    let h = !delta ? 0 : max === r ? 60 * ((g - b) / delta % 6) : max === g ? 60 * ((b - r) / delta + 2) : 60 * ((r - g) / delta + 4)
    if (h < 0) h += 360
    return { h, s: max ? delta / max * 100 : 0, v: max * 100, a }
}
export function hsvToRgb({ h, s, v, a = 1 }) {
    const hue = ((h % 360) + 360) % 360 / 60, chroma = v / 100 * s / 100, x = chroma * (1 - Math.abs(hue % 2 - 1)), m = v / 100 - chroma
    const channels = hue < 1 ? [chroma, x, 0] : hue < 2 ? [x, chroma, 0] : hue < 3 ? [0, chroma, x] : hue < 4 ? [0, x, chroma] : hue < 5 ? [x, 0, chroma] : [chroma, 0, x]
    return [...channels.map(channel => Math.round((channel + m) * 255)), a]
}
export function parseColorValue(value) {
    if (typeof value !== 'string' || value.length > 128) return null
    const text = value.trim()
    if (/^#(?:[\da-f]{3}|[\da-f]{6}|[\da-f]{8})$/i.test(text)) {
        let hex = text.slice(1); if (hex.length === 3) hex = hex.split('').map(char => char + char).join('')
        return rgbToHsv(...[0, 2, 4].map(i => parseInt(hex.slice(i, i + 2), 16)), hex.length === 8 ? parseInt(hex.slice(6), 16) / 255 : 1)
    }
    const match = text.match(/^(rgb|rgba|hsl|hsla)\(\s*(\d+(?:\.\d+)?)\s*,\s*(\d+(?:\.\d+)?)(%?)\s*,\s*(\d+(?:\.\d+)?)(%?)(?:\s*,\s*(\d+(?:\.\d+)?))?\s*\)$/i)
    if (!match) return null
    const kind = match[1].toLowerCase(), alpha = kind.endsWith('a'), hsl = kind.startsWith('hsl'), a = match[7] == null ? 1 : Number(match[7])
    const numbers = [match[2], match[3], match[5]].map(Number)
    if (alpha !== (match[7] != null) || a < 0 || a > 1) return null
    if (hsl) {
        if (match[4] !== '%' || match[6] !== '%' || numbers[0] > 360 || numbers[1] > 100 || numbers[2] > 100) return null
        const [h, saturation, lightness] = numbers, l = lightness / 100, s = saturation / 100, v = l + s * Math.min(l, 1 - l)
        return { h, s: v ? 2 * (1 - l / v) * 100 : 0, v: v * 100, a }
    }
    if (match[4] || match[6] || numbers.some(n => !Number.isInteger(n) || n > 255)) return null
    return rgbToHsv(...numbers, a)
}
const rounded = n => Math.round(n * 1000) / 1000
export function serializeColor(hsva, format = 'hex', alphaEnabled = false) {
    const [r, g, b] = hsvToRgb(hsva), a = alphaEnabled ? clamp(hsva.a, 0, 1) : 1
    if (format === 'rgb' || format === 'rgba') return format === 'rgba' && alphaEnabled || a < 1 ? `rgba(${r}, ${g}, ${b}, ${rounded(a)})` : `rgb(${r}, ${g}, ${b})`
    if (format === 'hsl' || format === 'hsla') {
        const saturation = hsva.s / 100, value = hsva.v / 100, l = value * (1 - saturation / 2), s = l === 0 || l === 1 ? 0 : (value - l) / Math.min(l, 1 - l)
        const channels = `${rounded(hsva.h)}, ${rounded(s * 100)}%, ${rounded(l * 100)}%`
        return format === 'hsla' && alphaEnabled || a < 1 ? `hsla(${channels}, ${rounded(a)})` : `hsl(${channels})`
    }
    const hex = '#' + [r, g, b].map(n => n.toString(16).padStart(2, '0')).join('')
    return (hex + (a < 1 ? Math.round(a * 255).toString(16).padStart(2, '0') : '')).toUpperCase()
}
export { clamp }
