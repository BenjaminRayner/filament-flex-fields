export const adjustmentNames = ['brightness', 'warmth', 'contrast', 'exposure', 'highlights', 'shadows', 'fade', 'saturation', 'vibrance', 'clarity', 'sharpness', 'vignette']
export const defaultAdjustments = () => Object.fromEntries(adjustmentNames.map(name => [name, 0]))
export const adjustmentRange = name => ({ min: ['fade', 'sharpness', 'vignette'].includes(name) ? 0 : -100, max: 100 })
export function normalizeAdjustment(name, value) {
    const number = Number(value)
    return adjustmentNames.includes(name) && Number.isFinite(number) ? Math.min(adjustmentRange(name).max, Math.max(adjustmentRange(name).min, number)) : null
}

/** Self-contained pixel transform shared by the worker and the sliced fallback. */
export function transformPixels(source, width, height, values, startRow = 0, endRow = height, target = new Uint8ClampedArray(source.length)) {
    const brightness = (values.brightness || 0) * .7, exposure = 2 ** ((values.exposure || 0) / 50)
    const contrast = 2 ** ((values.contrast || 0) / 100), saturation = 1 + (values.saturation || 0) / 100
    const warmth = (values.warmth || 0) * .3, fade = (values.fade || 0) / 100, sharpness = (values.sharpness || 0) / 100
    const clarity = (values.clarity || 0) / 100, vignette = (values.vignette || 0) / 100
    const shadows = (values.shadows || 0) * .6, highlights = (values.highlights || 0) * .6, vibranceAmount = (values.vibrance || 0) / 100
    const detailEnabled = sharpness !== 0 || clarity !== 0
    if (!Object.values(values).some(value => value !== 0)) {
        target.set(source.subarray(startRow * width * 4, endRow * width * 4), startRow * width * 4)
        return target
    }
    for (let y = startRow; y < endRow; y++) for (let x = 0; x < width; x++) {
        const index = (y * width + x) * 4
        let red = source[index], green = source[index + 1], blue = source[index + 2]
        const luminance = (.2126 * red + .7152 * green + .0722 * blue) / 255
        const tone = shadows * (1 - luminance) * (1 - luminance) + highlights * luminance * luminance
        const vibrance = vibranceAmount * (1 - (Math.max(red, green, blue) - Math.min(red, green, blue)) / 255)
        const left = x ? index - 4 : index, right = x < width - 1 ? index + 4 : index
        const up = y ? index - width * 4 : index, down = y < height - 1 ? index + width * 4 : index
        for (let channel = 0; channel < 3; channel++) {
            let number = (source[index + channel] * exposure + brightness + tone - 127.5) * contrast + 127.5
            number += channel === 0 ? warmth : channel === 2 ? -warmth : 0
            if (detailEnabled) {
                const detail = source[index + channel] - (source[left + channel] + source[right + channel] + source[up + channel] + source[down + channel]) / 4
                number += detail * sharpness * 1.5 + detail * clarity * (1 - Math.abs(luminance - .5))
            }
            if (channel === 0) red = number
            else if (channel === 1) green = number
            else blue = number
        }
        const gray = .2126 * red + .7152 * green + .0722 * blue
        const distance = ((x - width / 2) / (width / 2)) ** 2 + ((y - height / 2) / (height / 2)) ** 2
        const shade = 1 - Math.min(.85, distance * .32 * vignette)
        for (let channel = 0; channel < 3; channel++) {
            const number = gray + ((channel === 0 ? red : channel === 1 ? green : blue) - gray) * Math.max(0, saturation + vibrance)
            target[index + channel] = (number * (1 - fade * .45) + 42 * fade) * shade
        }
        target[index + 3] = source[index + 3]
    }
    return target
}
