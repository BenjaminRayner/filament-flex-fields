import { numericValue } from './value.js'

export function createStepperConfiguration(config = {}) {
    const state = {
        state: null, statePath: null, liveDebounce: null, min: null, max: null,
        step: 1, integer: true, nullable: false, disabled: false, nullLabel: '—',
        prefix: null, suffix: null, suffixForms: {}, decimalPlaces: null, locale: null,
        wheelAnimated: true, widthAnchor: '88',
    }
    for (const key of Object.keys(state)) if (config[key] !== undefined) state[key] = config[key]
    for (const name of ['integer', 'nullable', 'disabled', 'wheelAnimated']) {
        if (typeof state[name] !== 'boolean') throw new TypeError(`Invalid NumberStepper ${name}.`)
    }
    for (const name of ['min', 'max', 'step']) {
        const value = state[name]
        if (value !== null && numericValue(value) === null) throw new TypeError(`Invalid NumberStepper ${name}.`)
        if (value !== null) state[name] = Number(value)
    }
    if (state.step === null || state.step <= 0 || (state.min !== null && state.max !== null && state.min > state.max)) {
        throw new RangeError('NumberStepper requires a positive step and ordered bounds.')
    }
    if (state.decimalPlaces !== null && (!Number.isInteger(state.decimalPlaces) || state.decimalPlaces < 0 || state.decimalPlaces > 20)) {
        throw new RangeError('NumberStepper decimalPlaces must be between 0 and 20.')
    }
    if (state.liveDebounce !== null && (!Number.isInteger(state.liveDebounce) || state.liveDebounce < 0 || state.liveDebounce > 2147483647)) {
        throw new RangeError('Invalid NumberStepper live debounce.')
    }
    if (!state.suffixForms || typeof state.suffixForms !== 'object' || Array.isArray(state.suffixForms)) {
        // PHP serializes an empty associative array as []; normalize that empty value.
        if (Array.isArray(state.suffixForms) && state.suffixForms.length === 0) state.suffixForms = {}
        else throw new TypeError('Invalid NumberStepper suffix forms.')
    }
    for (const [category, text] of Object.entries(state.suffixForms)) {
        if (!['zero', 'one', 'two', 'few', 'many', 'other'].includes(category) || typeof text !== 'string') throw new TypeError('Invalid NumberStepper suffix form.')
    }
    if (Object.keys(state.suffixForms).length && typeof state.suffixForms.other !== 'string') throw new TypeError('NumberStepper suffix forms require other.')
    return { ...state, flowReady: false, flow: null, lastFlowValue: undefined, reducedMotion: false,
        _stepperInitialized: false, _stepperDisposed: false, _stepperReady: null,
        _stepperScope: null, _cancelCommit: null, _commitSnapshot: undefined,
        _limitPush: null, _limitAnimation: null, _iconAnimation: null, _lastSuffix: null,
        _stepperAnimations: new Set(),
    }
}
