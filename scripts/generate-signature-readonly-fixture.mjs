/** Convert the trusted Inkscape fixture to the pad's compact absolute M/L/Q/Z format. */
import fs from 'node:fs'
import path from 'node:path'

const root = path.resolve(import.meta.dirname, '..')
const source = fs.readFileSync(path.join(root, 'resources/fixtures/signature-readonly-preview.svg'), 'utf8')
const output = path.join(root, 'resources/fixtures/signature-readonly-preview.normalized.svg')
const viewBox = source.match(/viewBox="([^"]+)"/)?.[1].split(/\s+/).map(Number)
const translation = source.match(/transform="translate\(([^)]+)\)"/)?.[1].split(/[\s,]+/).map(Number) ?? [0, 0]
if (!viewBox || viewBox.length !== 4 || ![...viewBox, ...translation].every(Number.isFinite)) throw new Error('Invalid source geometry')
const width = 600, height = 260, padding = 12
const scale = Math.min((width - padding * 2) / viewBox[2], (height - padding * 2) / viewBox[3])
const fit = ([x, y]) => [(x + translation[0] - viewBox[0]) * scale + (width - viewBox[2] * scale) / 2, (y + translation[1] - viewBox[1]) * scale + (height - viewBox[3] * scale) / 2]
const midpoint = (a, b) => [(a[0] + b[0]) / 2, (a[1] + b[1]) / 2]
const coords = p => p.map(n => Number(n.toFixed(3))).join(',')
function quadratic(p0, p1, p2, p3, depth = 0) {
    const q = [(3 * (p1[0] + p2[0]) - p0[0] - p3[0]) / 4, (3 * (p1[1] + p2[1]) - p0[1] - p3[1]) / 4]
    const error = Math.max(Math.hypot(p1[0] - (p0[0] + 2 * q[0]) / 3, p1[1] - (p0[1] + 2 * q[1]) / 3), Math.hypot(p2[0] - (p3[0] + 2 * q[0]) / 3, p2[1] - (p3[1] + 2 * q[1]) / 3))
    if (error <= .025 || depth >= 12) return `Q${coords(q)} ${coords(p3)}`
    const a = midpoint(p0, p1), b = midpoint(p1, p2), c = midpoint(p2, p3), d = midpoint(a, b), e = midpoint(b, c), m = midpoint(d, e)
    return quadratic(p0, a, d, m, depth + 1) + quadratic(m, e, c, p3, depth + 1)
}
const paths = [...source.matchAll(/<path\b[^>]*\bd="([^"]+)"[^>]*>/g)].map(([, data]) => {
    const tokens = data.match(/[a-zA-Z]|[-+]?(?:\d*\.\d+|\d+\.?\d*)(?:[eE][-+]?\d+)?/g) ?? []
    let i = 0, command = '', current = [0, 0], start = current, result = ''
    const point = relative => {
        if (i + 1 >= tokens.length || !Number.isFinite(Number(tokens[i])) || !Number.isFinite(Number(tokens[i + 1]))) throw new Error('Invalid fixture coordinate')
        const p = [Number(tokens[i++]), Number(tokens[i++])]
        return relative ? [p[0] + current[0], p[1] + current[1]] : p
    }
    while (i < tokens.length) {
        if (/^[A-Za-z]$/.test(tokens[i])) command = tokens[i++]
        const relative = command === command.toLowerCase()
        switch (command.toUpperCase()) {
            case 'M': current = point(relative); start = current; result += `M${coords(fit(current))}`; command = relative ? 'l' : 'L'; break
            case 'L': current = point(relative); result += `L${coords(fit(current))}`; break
            case 'C': { const p1 = point(relative), p2 = point(relative), p3 = point(relative); result += quadratic(fit(current), fit(p1), fit(p2), fit(p3)); current = p3; break }
            case 'Z': result += 'Z'; current = start; command = ''; break
            default: throw new Error('Unsupported fixture path command: ' + command)
        }
    }
    return `<path d="${result}" fill="#18181b" fill-rule="evenodd"/>`
})
if (!paths.length) throw new Error('Fixture has no ink')
const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${width} ${height}">${paths.join('')}</svg>\n`
if (Buffer.byteLength(svg) > 48 * 1024) throw new Error('Normalized fixture exceeds the field budget')
if (process.argv.includes('--check')) {
    if (fs.readFileSync(output, 'utf8') !== svg) throw new Error('Rebuild the normalized signature fixture from its source')
} else {
    fs.writeFileSync(output, svg)
}
console.log('Normalized the source signature fixture:', Buffer.byteLength(svg), 'bytes')
