import { number, snap, ratio } from './value.js'

export function configureSlider(input) {
    const minValue = number(input.minValue ?? 0), maxValue = number(input.maxValue ?? 100)
    if (minValue === null || maxValue === null || maxValue <= minValue) throw Error('Invalid slider bounds.')
    const config = { ...input, minValue, maxValue, step: input.step ?? null, decimalPlaces: input.decimalPlaces ?? null,
        isRtl: !!input.isRtl, isVertical: !!input.isVertical, thumbStyle: input.thumbStyle ?? 'pill', elastic: !!input.elastic,
        stretchLimit: input.stretchLimit ?? 26, detentDistance: input.detentDistance ?? 7, marks: input.marks ?? [],
        behavior: input.behavior ?? 'tap', haptics: !!input.haptics }
    if (config.step !== null && (number(config.step) === null || config.step <= 0)) throw Error('Invalid slider step.')
    if (config.decimalPlaces !== null && (!Number.isInteger(config.decimalPlaces) || config.decimalPlaces < 0 || config.decimalPlaces > 20)) throw Error('Invalid slider precision.')
    if (!['pill', 'circle'].includes(config.thumbStyle) || !Number.isFinite(config.stretchLimit) || config.stretchLimit < 1 || config.stretchLimit > 80
        || !Number.isFinite(config.detentDistance) || config.detentDistance < 0 || config.detentDistance > 32) throw Error('Invalid slider presentation.')
    if (!Array.isArray(config.marks) || config.marks.length > 128 || config.marks.some(m => number(m.value) === null || m.value < minValue || m.value > maxValue)) throw Error('Invalid slider marks.')
    if (config.largeStep != null && (number(config.largeStep) === null || config.largeStep <= 0)) throw Error('Invalid slider large step.')
    if (config.step != null) config.step = Number(config.step)
    for (const key of ['minDifference', 'maxDifference']) if (config[key] != null && (number(config[key]) === null || config[key] < 0)) throw Error('Invalid slider handle gap.')
    if (config.minDifference != null && config.maxDifference != null && config.minDifference > config.maxDifference) throw Error('Invalid slider handle gap order.')
    const padding = config.rangePadding == null ? [0, 0] : Array.isArray(config.rangePadding) ? config.rangePadding : [config.rangePadding, config.rangePadding]
    if (padding.length !== 2 || padding.some(p => number(p) === null || p < 0) || Number(padding[0]) + Number(padding[1]) > maxValue - minValue) throw Error('Invalid slider padding.')
    const nonLinear = config.nonLinearPoints ?? {}
    if (typeof nonLinear !== 'object' || Array.isArray(nonLinear) || Object.keys(nonLinear).length > 128) throw Error('Invalid slider non-linear points.')
    config.points = Object.entries(nonLinear).map(([at, raw]) => {
        const value = Array.isArray(raw) ? raw[0] : raw
        if (!/^\d+(?:\.\d+)?%$/.test(at) || parseFloat(at) <= 0 || parseFloat(at) >= 100 || number(value) === null || value <= minValue || value >= maxValue
            || (Array.isArray(raw) && (raw.length !== 2 || number(raw[1]) === null || raw[1] <= 0))) throw Error('Invalid slider non-linear point.')
        return [parseFloat(at) / 100, Number(value)]
    }).sort((a, b) => a[0] - b[0])
    config.points = [[0, minValue], ...config.points, [1, maxValue]]
    for (let i = 1; i < config.points.length; i++) if (config.points[i][0] <= config.points[i - 1][0] || config.points[i][1] <= config.points[i - 1][1]) throw Error('Invalid slider non-linear order.')
    config.marks = config.marks.map(m => ({ ...m, ratio: ratio(Number(m.value), config) }))
    const initial = input.initialNormalizedValues ?? (Array.isArray(input.state) ? input.state : [input.state ?? minValue])
    if (!Array.isArray(initial) || !initial.length || initial.length > 128) throw Error('Invalid slider handle count.')
    config.initial = initial.map(v => snap(v, config))
    if (config.iconButton != null) {
        const button = config.iconButton
        if (config.initial.length !== 1 || !['start', 'end'].includes(button.position)) throw Error('An icon button requires one handle and a start/end position.')
        for (const value of [button.value, button.restoreValue]) {
            if (number(value) === null || value < minValue + Number(padding[0]) || value > maxValue - Number(padding[1])) throw Error('Invalid selectable icon button value.')
        }
        config.iconButton = Object.freeze({ ...button, value: snap(button.value, config), restoreValue: snap(button.restoreValue, config) })
        if ([config.iconButton.value, config.iconButton.restoreValue].some(value => value < minValue + Number(padding[0]) || value > maxValue - Number(padding[1]))) throw Error('Icon button step excludes the selectable value.')
        if (config.iconButton.value === config.iconButton.restoreValue) throw Error('Icon button restore value must differ from target.')
    }
    config.fillTrack = input.fillTrack ?? (input.autoFill ? (config.initial.length === 1 ? [true, false] : config.initial.map((_, i) => i > 0).concat(false)) : false)
    return Object.freeze(config)
}
