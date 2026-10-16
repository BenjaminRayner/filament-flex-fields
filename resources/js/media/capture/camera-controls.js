import { cameraOperation } from './operation.js'

const ranges = ['zoom', 'exposureCompensation', 'exposureTime', 'iso', 'colorTemperature', 'focusDistance', 'brightness', 'contrast', 'saturation', 'sharpness', 'pan', 'tilt', 'frameRate']
const modes = ['exposureMode', 'whiteBalanceMode', 'focusMode']
const manualModes = { exposureTime: 'exposureMode', iso: 'exposureMode', colorTemperature: 'whiteBalanceMode', focusDistance: 'focusMode' }
const safeRead = (track, method) => { try { return track[method]?.() ?? {} } catch { return {} } }

export function cameraRange(name, range, current) {
    if (!range || !Number.isFinite(range.min) || !Number.isFinite(range.max) || range.max <= range.min) return null
    const step = Number.isFinite(range.step) && range.step > 0 ? range.step : (range.max - range.min) / 100
    const value = Number.isFinite(current) ? Math.max(range.min, Math.min(range.max, current)) : range.min
    return { name, kind: 'range', min: range.min, max: range.max, step, value }
}

export function discoverCameraControls(capabilities = {}, settings = {}) {
    const controls = []
    for (const name of ranges) {
        const mode = manualModes[name]
        if (mode && Array.isArray(capabilities[mode]) && !capabilities[mode].includes('manual')) continue
        const control = cameraRange(name, capabilities[name], settings[name])
        if (control) controls.push(control)
    }
    for (const name of modes) {
        const options = [...new Set((Array.isArray(capabilities[name]) ? capabilities[name] : []).filter(value => ['none', 'manual', 'single-shot', 'continuous'].includes(value)))]
        if (options.length > 1) controls.push({ name, kind: 'mode', options, value: options.includes(settings[name]) ? settings[name] : options[0] })
    }
    for (const name of ['torch', 'backgroundBlur']) {
        const values = capabilities[name]
        if (values === true || (Array.isArray(values) && values.includes(true) && values.includes(false))) controls.push({ name, kind: 'toggle', value: settings[name] === true })
    }
    return controls
}

/** One owned track, one serialized constraint write, latest pending input per setting. */
export function createCameraControls({ owner, track, changed, failed, view = globalThis }) {
    let capabilities = safeRead(track, 'getCapabilities')
    let controls = discoverCameraControls(capabilities, safeRead(track, 'getSettings'))
    let desired = {}, committed = {}, pending = false, running = null, frame = null
    const initialConstraints = safeRead(track, 'getConstraints'), managed = new Set()
    let disposed = false
    const active = () => !disposed && !owner.disposed
    const publish = busy => { if (active()) changed(controls.map(control => ({ ...control })), busy) }
    function refresh() {
        const settings = safeRead(track, 'getSettings')
        capabilities = safeRead(track, 'getCapabilities')
        controls = discoverCameraControls(capabilities, settings)
        controls = controls.map(control => ({ ...control, value: (pending ? desired[control.name] : undefined) ?? settings[control.name] ?? committed[control.name] ?? control.value }))
    }
    async function drain() {
        while (pending && active()) {
            pending = false
            const patch = { ...desired }
            try {
                const advanced = [...(initialConstraints.advanced ?? []).map(part => Object.fromEntries(Object.entries(part).filter(([key]) => !managed.has(key)))).filter(part => Object.keys(part).length), patch]
                await cameraOperation(track.applyConstraints({ ...initialConstraints, advanced }), owner)
                if (!active()) return
                const settings = safeRead(track, 'getSettings')
                for (const [name, value] of Object.entries(patch)) {
                    const control = controls.find(item => item.name === name)
                    const actual = settings[name]
                    const mismatch = actual !== undefined && (typeof value === 'number' ? Math.abs(actual - value) > (control?.step ?? 0) / 2 + 1e-6 : actual !== value)
                    if (mismatch) throw new Error(`Camera did not apply ${name}`)
                }
                committed = patch
            } catch (error) {
                if (!active()) return
                // Preserve the stream, roll back rejected requested values and report separately.
                if (!pending) desired = { ...committed }
                failed(error)
            }
            refresh()
            publish(true)
        }
    }
    function start() {
        if (!active() || running) return running
        running = drain().finally(() => { running = null; publish(false); if (pending && active()) schedule() })
        publish(true)
        return running
    }
    function schedule() {
        if (frame !== null || running || !active()) return
        frame = view.requestAnimationFrame(() => { frame = null; void start() })
    }
    owner.defer(() => { disposed = true; if (frame !== null) view.cancelAnimationFrame(frame); frame = null; pending = false; desired = committed = {}; capabilities = {}; controls = []; managed.clear() })
    publish(false)
    return {
        set(name, input) {
            if (!active()) return
            const control = controls.find(item => item.name === name)
            if (!control) return
            let value = input
            if (control.kind === 'range') {
                const number = Number(input)
                if (!Number.isFinite(number)) return
                const steps = Math.round((Math.max(control.min, Math.min(control.max, number)) - control.min) / control.step)
                value = Math.min(control.max, Number((control.min + steps * control.step).toPrecision(12)))
            } else if (control.kind === 'mode') { if (!control.options.includes(value)) return }
            else if (typeof value !== 'boolean') return
            const mode = manualModes[name]
            if (mode && capabilities[mode]?.includes('manual')) { desired[mode] = 'manual'; managed.add(mode) }
            if (modes.includes(name) && value !== 'manual') {
                for (const [dependent, modeName] of Object.entries(manualModes)) if (modeName === name) { delete desired[dependent]; managed.add(dependent) }
            }
            managed.add(name)
            desired[name] = value
            control.value = value
            pending = true
            publish(true)
            schedule()
        },
        async flush() {
            if (frame !== null) view.cancelAnimationFrame(frame)
            frame = null
            while (pending || running) { owner.assertActive(); await (running ?? start()) }
            owner.assertActive()
        },
        get capabilities() { return capabilities },
    }
}
