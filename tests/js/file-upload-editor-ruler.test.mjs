import assert from 'node:assert/strict'
import { test } from 'node:test'
import { rulerValue, rulerSpacing, rulerStep, bindEditorRuler } from '../../resources/js/file-upload/editor/ruler.js'
import { rulerTickPulse, createRulerTickMotion } from '../../resources/js/file-upload/editor/ruler-tick-motion.js'
import { adjustmentRange } from '../../resources/js/file-upload/editor/adjustments.js'
import { createMediaOwner } from '../../resources/js/media/lifecycle/owner.js'

function fixture({ reduced = false, value = 0, min = -100, max = 100 } = {}) {
    const owner = createMediaOwner(), input = new EventTarget(), changed = [], frames = new Map(), haptics = []
    const strip = { style: {}, children: [], replaceChildren(fragment) { this.children = fragment.children } }
    let id = 0, capture = null, time = 0, state = { value, min, max }
    const document = {
        createDocumentFragment: () => ({ children: [], append(element) { this.children.push(element) } }),
        createElement: () => ({ style: {}, dataset: {}, classList: { add() {} } }),
    }
    Object.assign(input, { value: String(value), min: String(min), max: String(max), ownerDocument: document,
        closest: () => ({ querySelector: () => strip }), focus() {}, setPointerCapture: value => { capture = value },
        hasPointerCapture: value => value === capture, releasePointerCapture: () => { capture = null } })
    const view = { performance: { now: () => time }, navigator: { vibrate: duration => haptics.push(duration) }, matchMedia: () => ({ matches: reduced }),
        requestAnimationFrame: fn => { frames.set(++id, fn); return id }, cancelAnimationFrame: id => frames.delete(id) }
    const ruler = bindEditorRuler({ owner, input, view, read: () => state, change: value => { changed.push(value); state.value = value } })
    const emit = (name, x, pointerType = 'mouse') => {
        const event = new Event(name, { cancelable: true })
        Object.assign(event, { clientX: x, pointerId: 1, isPrimary: true, button: 0, pointerType }); input.dispatchEvent(event)
    }
    const step = (ms = 16) => { time += ms; const pending = [...frames.values()]; frames.clear(); for (const fn of pending) fn(time) }
    const finish = () => { for (let i = 0; frames.size && i < 80; i++) step(); assert.equal(frames.size, 0) }
    return { owner, input, strip, changed, frames, haptics, ruler, emit, step, finish, get capture() { return capture }, setState: next => { state = next } }
}

test('five units per demo tick, magnetic targets and adjustment-specific finite limits', () => {
    assert.equal(rulerValue(0, -rulerSpacing, -100, 100), 5)
    assert.equal(rulerValue(10, rulerSpacing / 2, -100, 100), 10)
    assert.equal(rulerValue(0, 2000, 0, 100), 0)
    assert.equal(rulerValue(99, -2000, -100, 100), 100)
    assert.deepEqual(adjustmentRange('brightness'), { min: -100, max: 100 })
    assert.deepEqual(adjustmentRange('fade'), { min: 0, max: 100 })
})
test('drag follows fractional movement while image settings stay integer; release aligns exactly', () => {
    const f = fixture()
    f.emit('pointerdown', 100); assert.deepEqual(f.changed, [])
    f.emit('pointermove', 86); f.emit('pointermove', 65)
    assert.equal(f.frames.size, 1); assert.deepEqual(f.changed, [])
    f.step(); assert.deepEqual(f.changed, [16]); assert.ok(Math.abs(parseFloat(f.strip.style.transform.slice(11)) + 255) < 1e-9)
    f.emit('pointerup', 65); assert.equal(f.capture, null)
    // A RAF timestamp can precede performance.now() inside the pointer event.
    f.step(-1); assert.ok(Math.abs(parseFloat(f.strip.style.transform.slice(11)) + 255) < 1e-9)
    f.finish(); assert.equal(f.strip.style.transform, 'translateX(-253px)')
    assert.deepEqual(f.changed, [16, 15]); assert.ok(f.strip.children.every(tick => !tick.style.transform))
    f.owner.dispose()
})
test('finite strip ends at center at both boundaries and reversing overshoot has no dead zone', () => {
    const f = fixture()
    assert.equal(f.strip.children.filter(t => t.dataset.value !== undefined).length, 41)
    assert.equal(f.strip.children[0].dataset.value, '-100'); assert.equal(f.strip.children.filter(t => t.dataset.value !== undefined).at(-1).dataset.value, '100')
    f.emit('pointerdown', 0); f.emit('pointermove', 3000); f.step()
    assert.equal(f.input.value, '-100'); assert.equal(f.strip.style.transform, 'translateX(0px)')
    f.emit('pointermove', 2989); f.emit('pointerup', 2989); f.finish()
    assert.equal(f.input.value, '-95')
    f.emit('pointerdown', 0); f.emit('pointermove', -5000); f.emit('pointerup', -5000); f.finish()
    assert.equal(f.input.value, '100'); assert.equal(f.strip.style.transform, 'translateX(-440px)')
    f.owner.dispose()
})
test('passed ticks return independently with the measured demo spring, rather than a speed-based crest', () => {
    for (const [age, inset] of [[48.1, 5.48476], [114.6, 20.6658], [181.3, 34.422], [248, 43.4174], [448.5, 50.7402]]) {
        assert.ok(Math.abs(50 * (1 - rulerTickPulse(age)) - inset) < .3)
    }
    const f = fixture()
    f.emit('pointerdown', 100); f.emit('pointermove', 67); f.step()
    const passed = f.strip.children.filter(tick => tick.style.clipPath)
    assert.equal(passed.length, 4)
    assert.ok(passed.every(tick => tick.style.clipPath === 'inset(0% 0 0)'))
    f.step(64)
    const previous = passed[0].style.clipPath
    f.emit('pointermove', 56); f.step()
    assert.notEqual(passed[0].style.clipPath, previous)
    assert.equal(f.strip.children.find(tick => tick.dataset.value === '20').style.clipPath, 'inset(0% 0 0)')
    f.emit('pointerup', 56); f.finish()
    assert.equal(f.strip.children.filter(tick => tick.style.clipPath).length, 1)
    assert.equal(f.strip.children.find(tick => tick.dataset.value === f.input.value).style.backgroundColor, '#f4d845')
    f.owner.dispose()
})
test('switching adjustment cancels pending motion and rebuilds one-sided scale; disposal leaves no work', () => {
    const f = fixture()
    f.emit('pointerdown', 0); f.emit('pointermove', -31); f.step(); f.emit('pointerup', -31)
    f.setState({ ...adjustmentRange('fade'), value: 40 }); f.ruler.sync()
    assert.equal(f.frames.size, 0); assert.equal(f.capture, null)
    assert.equal(f.strip.children.filter(t => t.dataset.value !== undefined).length, 21); assert.equal(f.strip.children[0].dataset.value, '0')
    assert.equal(f.strip.style.transform, 'translateX(-88px)')
    f.emit('pointerdown', 0); f.emit('pointermove', -14); f.owner.dispose()
    assert.equal(f.frames.size, 0); assert.equal(f.capture, null)
    f.emit('pointerup', -14); assert.deepEqual(f.changed, [14, 15])
})
test('keyboard remains native, reduced motion snaps immediately, touch haptics are throttled', () => {
    const f = fixture({ reduced: true })
    f.input.value = '15'; f.emit('input', 0)
    assert.deepEqual(f.changed, [15]); assert.equal(f.strip.style.transform, 'translateX(-253px)')
    f.emit('pointerdown', 0); f.emit('pointerup', -35)
    assert.equal(f.input.value, '30'); assert.equal(f.strip.style.transform, 'translateX(-286px)')
    f.finish(); assert.ok(f.strip.children.every(tick => !tick.style.transform))
    assert.deepEqual(f.haptics, [])
    f.emit('pointerdown', 0, 'touch'); f.emit('pointermove', -11, 'touch'); f.step()
    f.emit('pointermove', -22, 'touch'); f.step(); assert.deepEqual(f.haptics, [5])
    f.emit('pointermove', -33, 'touch'); f.step(50); assert.deepEqual(f.haptics, [5, 5])
    f.owner.dispose()
})

test('yellow is the moving notch, disappears in the gap and hands its height back to a return spring', () => {
    const ticks = Array.from({ length: 41 }, () => ({ style: {} }))
    const motion = createRulerTickMotion({ ticks, min: -100, step: rulerStep, spacing: rulerSpacing, reduced: false })
    assert.equal(motion.paint(0, 0), false)
    assert.equal(ticks[20].style.backgroundColor, '#f4d845')
    motion.pass(0, 3, 10); motion.paint(10, 3)
    assert.ok(ticks.every(tick => tick.style.backgroundColor !== '#f4d845'))
    assert.equal(ticks[20].style.clipPath, 'inset(0% 0 0)')
    assert.ok(!ticks[21].style.clipPath) // A notch ahead of the pointer must not flash early.
    motion.paint(80, 5)
    assert.equal(ticks[21].style.backgroundColor, '#f4d845')
    assert.notEqual(ticks[20].style.clipPath, 'inset(0% 0 0)')
    motion.paint(900, 5)
    assert.equal(ticks[20].style.clipPath, '')
    assert.equal(ticks[21].style.clipPath, 'inset(0% 0 0)')
    motion.reset()
    assert.ok(ticks.every(tick => !tick.style.clipPath && !tick.style.backgroundColor))
})

test('Save or switching tools commits the final pending gesture before terminating it', () => {
    const f = fixture()
    f.emit('pointerdown', 0); f.emit('pointermove', -35)
    f.ruler.finish()
    assert.deepEqual(f.changed, [15])
    assert.equal(f.input.value, '15'); assert.equal(f.capture, null); assert.equal(f.input.step, '5')
    assert.equal(f.strip.style.transform, 'translateX(-253px)')
    f.finish(); f.owner.dispose()
})
