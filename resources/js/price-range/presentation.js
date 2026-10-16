import { createDecimalFormatter } from '../core/number-format.js'

export function createRangePresentation(root, configuration, policy) {
    const track = root.querySelector('[data-price-track]')
    const fill = root.querySelector('[data-price-fill]')
    const thumbs = Object.fromEntries([...root.querySelectorAll('[data-price-thumb]')].map(node => [node.dataset.priceThumb, node]))
    const inputs = Object.fromEntries([...root.querySelectorAll('[data-price-input]')].map(node => [node.dataset.priceInput, node]))
    let lastPair = null, lastDisabled = null
    const formatter = createDecimalFormatter({ locale: configuration.locale, decimalPlaces: configuration.integer ? 0 : configuration.decimalPlaces })
    const format = value => `${configuration.prefix ?? ''}${formatter(value)}`
    const attribute = (node, name, value) => { const text = String(value); if (node.getAttribute(name) !== text) node.setAttribute(name, text) }
    const paint = (pair, disabled, force = false) => {
        const changed = force || !lastPair || lastPair.min !== pair.min || lastPair.max !== pair.max
        if (disabled !== lastDisabled) {
            root.classList.toggle('is-disabled', disabled)
            for (const node of [...Object.values(thumbs), ...Object.values(inputs)]) node.disabled = disabled
            lastDisabled = disabled
        }
        if (!changed) return
        const min = policy.percent(pair.min), max = policy.percent(pair.max)
        fill.style.insetInlineStart = `${min}%`; fill.style.width = `${max - min}%`
        for (const edge of ['min', 'max']) {
            const node = thumbs[edge]
            node.style.insetInlineStart = `${edge === 'min' ? min : max}%`
            attribute(node, 'aria-valuenow', pair[edge]); attribute(node, 'aria-valuetext', format(pair[edge]))
            attribute(node, 'aria-valuemin', edge === 'min' ? policy.min : pair.min)
            attribute(node, 'aria-valuemax', edge === 'max' ? policy.max : pair.max)
            const input = inputs[edge]
            if (input && (force || root.ownerDocument.activeElement !== input)) input.value = String(pair[edge])
        }
        root.style.setProperty('--fff-price-range-min-percent', min)
        root.style.setProperty('--fff-price-range-max-percent', max)
        lastPair = { ...pair }
    }
    return { track, inputs, thumbs, paint }
}
