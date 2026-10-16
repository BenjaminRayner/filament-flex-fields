import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const source = await readFile(new URL('../../resources/js/file-upload/editor/adjustments.js', import.meta.url), 'utf8')
const { transformPixels, defaultAdjustments, adjustmentNames, normalizeAdjustment } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`)
let checks = 0
const check = (condition, message) => { assert.ok(condition, message); checks++ }
const width = 37, height = 29
const pixels = Uint8ClampedArray.from({ length: width * height * 4 }, (_, index) => (index * 19 + Math.floor(index / 13) * 37) % 256)
const original = pixels.slice()
assert.deepEqual(transformPixels(pixels, width, height, defaultAdjustments()), pixels); checks++
const workerTransform = new Function(`return (${transformPixels.toString()})`)()
for (const name of adjustmentNames) {
    check(normalizeAdjustment(name, Infinity) === null && normalizeAdjustment(name, 1000) === 100, `${name}: finite bounded inputs`)
    const values = { ...defaultAdjustments(), [name]: 65 }
    const whole = transformPixels(pixels, width, height, values)
    check(whole.some((value, index) => index % 4 !== 3 && value !== pixels[index]), `${name}: has a real pixel effect`)
    check(whole.every((value, index) => index % 4 !== 3 || value === pixels[index]), `${name}: preserves source transparency`)
    const sliced = new Uint8ClampedArray(pixels.length)
    for (let row = 0; row < height; row += 7) transformPixels(pixels, width, height, values, row, Math.min(row + 7, height), sliced)
    assert.deepEqual(whole, sliced, `${name}: fallback equals worker algorithm`); checks++
    assert.deepEqual(whole, workerTransform(pixels, width, height, values), `${name}: self-contained worker function`); checks++
}
assert.deepEqual(pixels, original, 'Each adjustment reads immutable original pixels, without cumulative recompression'); checks++
const neutral = new Uint8ClampedArray([96, 96, 96, 255, 160, 160, 160, 72])
const bright = transformPixels(neutral, 2, 1, { brightness: 20 })
check(bright[0] > neutral[0] && bright[4] > neutral[4], 'Brightness increases both midtones')
const warm = transformPixels(neutral, 2, 1, { warmth: 20 })
check(warm[0] > neutral[0] && warm[2] < neutral[2], 'Warmth balances red and blue')
const contrast = transformPixels(neutral, 2, 1, { contrast: 20 })
check(contrast[0] < neutral[0] && contrast[4] > neutral[4], 'Contrast expands tones around midpoint')
const monochrome = transformPixels(new Uint8ClampedArray([30, 110, 170, 255]), 1, 1, { saturation: -100 })
check(monochrome[0] === monochrome[1] && monochrome[1] === monochrome[2], 'Minimum saturation is neutral grayscale')
const large = new Uint8ClampedArray(1024 * 1024 * 4).fill(128)
const started = performance.now()
transformPixels(large, 1024, 1024, Object.fromEntries(adjustmentNames.map(name => [name, 30])))
console.log(`Image editor pixels: ${checks} checks passed; 12 effects, immutable source, alpha, worker/fallback parity. 1MP all-effects transform: ${(performance.now() - started).toFixed(1)}ms on this machine. No claim of proprietary filter equivalence.`)
