import test from 'node:test'
import assert from 'node:assert/strict'
import noUiSlider from 'nouislider'
import { createSliderEngine } from '../../resources/js/flex-slider/engines/nouislider.js'
import { configureSlider } from '../../resources/js/flex-slider/configuration.js'

for (const start of [50, [20, 80]]) test(`labelled hardware slider mounts for ${Array.isArray(start) ? 'two' : 'one'} handles`, () => {
    const original = noUiSlider.create, stop = new Error('configuration captured')
    let config
    noUiSlider.create = (_host, options) => { config = options; throw stop }
    try {
        const values = Array.isArray(start) ? start : [start]
        const options = configureSlider({ state: start, initialNormalizedValues: values, handleLabel: 'Exposure', minValue: 0, maxValue: 100 })
        assert.throws(() => createSliderEngine({ refs: { sliderHost: {} }, root: {}, scope: {}, config: options, current: () => values }), error => error === stop)
        assert.equal(config.handleAttributes.length, values.length)
        assert.ok(config.handleAttributes.every(handle => handle['aria-label'] === 'Exposure'))
        assert.deepEqual(config.start, values)
    } finally { noUiSlider.create = original }
})
