import { it } from 'node:test'
import assert from 'node:assert/strict'
import { createConfirmation } from '../../resources/js/confirmation/controller.js'
import { createResourceScope } from '../../resources/js/runtime/lifecycle/resource-scope.js'
import { createDeadline } from '../../resources/js/confirmation/deadline.js'
import { createHold } from '../../resources/js/confirmation/hold.js'
import { configureConfirmation } from '../../resources/js/confirmation/configuration.js'

function target() {
    const listeners = new Map(), attributes = new Map()
    return {
        dataset: {}, style: { setProperty() {} },
        addEventListener(name, handler) { if (!listeners.has(name)) listeners.set(name, new Set()); listeners.get(name).add(handler) },
        removeEventListener(name, handler) { listeners.get(name)?.delete(handler) },
        emit(name, event) { for (const handler of [...listeners.get(name) ?? []]) handler(event) },
        dispatchEvent(event) { this.emit(event.type, event); return true },
        setAttribute(name, value) { attributes.set(name, value) }, removeAttribute(name) { attributes.delete(name) },
        getAttribute(name) { return attributes.get(name) ?? null }, hasAttribute(name) { return attributes.has(name) },
        listenerCount: () => [...listeners.values()].reduce((total, list) => total + list.size, 0),
        closest: () => null,
    }
}
function fixture(options = {}) {
    const before = { raf: globalThis.requestAnimationFrame, caf: globalThis.cancelAnimationFrame }
    let now = 0, serial = 0, requests = 0, resolve, reject
    const frames = new Map(), overlay = target(), feedback = { hidden: true, querySelectorAll: () => [] }
    const button = Object.assign(target(), { disabled: false, querySelector: selector => selector === '[data-hold-feedback]' ? feedback : overlay,
        getBoundingClientRect: () => ({ left: 0, top: 0, right: 100, bottom: 40 }), focus() {} })
    button.dataset.confirmRole = 'hold'; button.closest = () => button
    const view = Object.assign(target(), { performance: { now: () => now }, MutationObserver: null, matchMedia: () => ({ matches: false }), CustomEvent,
        navigator: { vibrate() {} } })
    const doc = Object.assign(target(), { defaultView: view, body: target(), hidden: false })
    const root = Object.assign(target(), { isConnected: true, ownerDocument: doc, parentElement: doc.body,
        contains: element => element === button || element === root,
        querySelector: selector => selector === '[data-confirm-role="hold"]' ? button : null })
    doc.activeElement = button
    globalThis.requestAnimationFrame = callback => { const id = ++serial; frames.set(id, callback); return id }
    globalThis.cancelAnimationFrame = id => frames.delete(id)
    const runtime = createConfirmation({ duration: 200, actionName: 'archive', resultTimeout: 0,
        labels: { pending: 'Working', done: 'Done', error: 'Failed', retry: 'Retry' },
        execute: () => { requests++; return new Promise((ok, fail) => { resolve = ok; reject = fail }) }, ...options })
    runtime.$el = root; runtime.$wire = { mountedActions: [] }
    const event = (overrides = {}) => ({ target: button, button: 0, isPrimary: true, pointerId: 1, preventDefault() {}, stopPropagation() {}, ...overrides })
    return { runtime, root, button, feedback, view, doc, frames,
        emit(type, overrides) { root.emit(type, event(overrides)) },
        step(ms) { now += ms; const ready = [...frames.values()]; frames.clear(); ready.forEach(callback => callback(now)) },
        resolve(value = { fffConfirmation: true, action: 'archive', operation: 'confirm', success: true }) { resolve(value) },
        reject() { reject(Error('network failed')) }, get requests() { return requests },
        restore() { runtime.destroy(); globalThis.requestAnimationFrame = before.raf; globalThis.cancelAnimationFrame = before.caf },
    }
}
const settle = () => new Promise(resolve => setImmediate(resolve))

it('has no idle animation frames and initialization/disposal are idempotent', async () => {
    const f = fixture()
    try {
        const init = f.runtime.init(); assert.equal(f.runtime.init(), init); await init
        assert.equal(f.frames.size, 0); assert.equal(f.view.listenerCount(), 0)
        f.runtime.destroy(); f.runtime.destroy()
        assert.equal(f.runtime.getResourceSnapshot().resources, 0)
        assert.equal(f.root.listenerCount(), 0); assert.equal(f.doc.listenerCount(), 0)
    } finally { f.restore() }
})

it('keeps loading until the real server promise settles and never submits twice', async () => {
    const f = fixture()
    try {
        await f.runtime.init(); f.emit('pointerdown'); f.step(200)
        assert.equal(f.requests, 1); assert.equal(f.root.dataset.state, 'pending'); assert.equal(f.feedback.hidden, false)
        assert.equal(f.button.disabled, true); assert.equal(f.frames.size, 0)
        f.emit('pointerdown'); f.emit('click'); f.emit('keydown', { key: 'Escape' })
        assert.equal(f.requests, 1); assert.equal(f.root.dataset.state, 'pending')
        f.resolve(); await settle()
        assert.equal(f.root.dataset.state, 'done'); assert.equal(f.button.disabled, false)
    } finally { f.restore() }
})

it('treats explicit server failure, missing receipts and transport rejection as errors', async () => {
    for (const failure of ['receipt', 'missing', 'transport']) {
        const f = fixture()
        try {
            await f.runtime.init(); f.emit('pointerdown'); f.step(200)
            if (failure === 'transport') f.reject()
            else f.resolve(failure === 'missing' ? null : { fffConfirmation: true, action: 'archive', operation: 'confirm', success: false })
            await settle(); assert.equal(f.root.dataset.state, 'error'); assert.equal(f.feedback.hidden, false)
            f.step(350); f.emit('click'); assert.equal(f.requests, 2); assert.equal(f.root.dataset.state, 'pending')
            f.resolve(); await settle(); assert.equal(f.root.dataset.state, 'done')
        } finally { f.restore() }
    }
})

it('does not fabricate completion when native confirmation mounts a form', async () => {
    const f = fixture()
    try {
        await f.runtime.init(); f.emit('pointerdown'); f.step(200)
        f.runtime.$wire.mountedActions = [{ name: 'archive' }]
        f.resolve(null); await settle(); assert.equal(f.root.dataset.state, 'idle')
    } finally { f.restore() }
})

it('cancels early pointer release, leaving bounds, keyboard release, resize and blur', async () => {
    for (const cancel of ['up', 'outside', 'keyboard', 'resize', 'blur', 'hidden']) {
        const f = fixture()
        try {
            await f.runtime.init()
            cancel === 'keyboard' ? f.emit('keydown', { key: ' ' }) : f.emit('pointerdown')
            f.step(100)
            if (cancel === 'up') f.view.emit('pointerup', { pointerId: 1 })
            else if (cancel === 'outside') f.view.emit('pointermove', { pointerId: 1, clientX: 110, clientY: 20 })
            else if (cancel === 'keyboard') f.emit('keyup', { key: ' ' })
            else if (cancel === 'hidden') { f.doc.hidden = true; f.doc.emit('visibilitychange', {}) }
            else f.view.emit(cancel, {})
            f.step(1000)
            assert.equal(f.requests, 0); assert.equal(f.frames.size, 0); assert.equal(f.runtime.getResourceSnapshot().holding, false)
            assert.equal(f.view.listenerCount(), 0)
        } finally { f.restore() }
    }
})

it('ignores right-click, secondary touches and held keyboard repeats', async () => {
    const f = fixture()
    try {
        await f.runtime.init(); f.emit('pointerdown', { button: 2 }); f.emit('pointerdown', { isPrimary: false }); f.emit('keydown', { key: ' ', repeat: true })
        f.step(1000); assert.equal(f.requests, 0); assert.equal(f.frames.size, 0)
    } finally { f.restore() }
})

it('retires active frames and ignores a late server response after destruction', async () => {
    const f = fixture()
    try {
        await f.runtime.init(); f.emit('pointerdown'); f.step(200)
        f.runtime.destroy(); f.resolve(); await settle()
        assert.equal(f.root.dataset.state, 'pending'); assert.equal(f.runtime.getResourceSnapshot().resources, 0)
        assert.equal(f.frames.size, 0); assert.equal(f.view.listenerCount(), 0)
    } finally { f.restore() }
})

it('runs holds on elapsed time rather than frame count', () => {
    let now = 0, frame, completions = 0
    const scope = { disposed: false, defer() {}, frame(callback) { frame = callback; return () => { frame = null } } }
    const hold = createHold({ scope, duration: 1000, releaseDuration: 200, sweep: 'right', paint() {}, complete() { completions++ }, clock: () => now })
    hold.start(); now = 900; frame(); assert.equal(completions, 0)
    now = 1000; frame(); assert.equal(completions, 1)
})

it('pauses timeout deadlines without scheduling repeated per-frame timers', async () => {
    const scope = createResourceScope(), deadline = createDeadline(scope)
    let finished = 0
    deadline.start(30, () => finished++)
    deadline.pause(true); await new Promise(resolve => setTimeout(resolve, 40)); assert.equal(finished, 0)
    deadline.pause(false); await new Promise(resolve => setTimeout(resolve, 40)); assert.equal(finished, 1)
    scope.dispose(); assert.equal(scope.snapshot().resources, 0)
})

it('rejects invalid runtime configuration instead of accepting zero duration or unsupported modes', () => {
    for (const options of [{ duration: 0 }, { mode: 'unknown' }, { countdown: 31 }, { variant: 'unknown' }]) assert.throws(() => configureConfirmation(options))
})
