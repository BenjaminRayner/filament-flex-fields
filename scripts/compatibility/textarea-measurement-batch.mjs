import assert from 'node:assert/strict'
import { enqueueTextareaMeasurement } from '../../resources/js/textarea/measurement-batch.js'
import { createTextareaAutosize } from '../../resources/js/textarea/autosize.js'
import { createResourceScope } from '../../resources/js/runtime/lifecycle/resource-scope.js'

const originalFrame = globalThis.requestAnimationFrame, originalCancel = globalThis.cancelAnimationFrame
const frames = new Map()
let id = 0
globalThis.requestAnimationFrame = callback => { frames.set(++id, callback); return id }
globalThis.cancelAnimationFrame = key => frames.delete(key)
const flush = async () => {
    for (const [key, callback] of [...frames]) { frames.delete(key); callback() }
    await Promise.resolve()
}
try {
    // Phase ordering is observable: a write from one owner may not split the
    // measurements of another. Cancellation and exceptions remain owner-local.
    const document = {}, trace = []
    const task = name => Object.fromEntries(['read', 'prepare', 'height', 'commit'].map(phase => [phase, () => { trace.push(`${phase}:${name}`); return {} }]))
    const retired = enqueueTextareaMeasurement(document, task('retired'))
    const first = enqueueTextareaMeasurement(document, task('a'))
    const failed = enqueueTextareaMeasurement(document, { ...task('bad'), read() { throw Error('measurement failed') } })
    const failure = assert.rejects(failed.promise, /measurement failed/)
    const last = enqueueTextareaMeasurement(document, task('b'))
    retired.cancel()
    assert.equal(frames.size, 1)
    await flush()
    assert.deepEqual(trace, ['read:a', 'read:b', 'prepare:a', 'prepare:b', 'height:a', 'height:b', 'commit:a', 'commit:b'])
    assert.deepEqual(await Promise.all([retired.promise, first.promise, last.promise]), [false, true, true])
    await failure

    let dirty = true, flushes = 0, removed = 0, fontReads = 0, resolveFonts
    const readLayout = () => { if (dirty) { flushes++; dirty = false } }
    const fontsReady = new Promise(resolve => { resolveFonts = resolve })
    const listeners = new Set()
    const ownerDocument = {
        documentElement: {},
        fonts: {
            status: 'loading',
            get ready() { readLayout(); fontReads++; return fontsReady },
            addEventListener(name, fn) { listeners.add(fn) },
            removeEventListener(name, fn) { listeners.delete(fn) },
        },
        defaultView: {
            getComputedStyle() { readLayout(); return { fontSize: '16px', maxHeight: '100px', paddingLeft: '8px', paddingRight: '8px' } },
            ResizeObserver: class { observe() {} disconnect() {} },
        },
        createElement() {
            return {
                style: new Proxy({}, { set(target, key, value) { dirty = true; target[key] = value; return true } }),
                setAttribute() {}, remove() { removed++ },
                get scrollHeight() { readLayout(); return this.value.length > 10 ? 180 : 20 },
            }
        },
    }
    const owners = Array.from({ length: 300 }, (_, index) => {
        const scope = createResourceScope()
        const input = { ownerDocument, isConnected: true, value: index % 2 ? 'short' : 'A long example note', style: {}, get clientWidth() { readLayout(); return 300 } }
        const root = { querySelector: () => ({ appendChild() { dirty = true } }) }
        const autosize = createTextareaAutosize(root, input, { shouldAutosize: true, initialHeightRem: 2.25 }, scope)
        return { scope, input, autosize, ready: autosize.initialize() }
    })
    assert.equal(fontReads, 0, 'Creating mirrors must not flush font readiness per owner')
    owners[0].scope.dispose()
    await flush()
    const readiness = await Promise.all(owners.map(owner => owner.ready))
    assert.equal(readiness.filter(Boolean).length, 299)
    assert.equal(flushes, 2, 'All active owners share the input-read and mirror-read layout phases')
    for (const [index, owner] of owners.entries()) if (index) assert.equal(owner.input.style.height, index % 2 ? '36px' : '100px')
    await flush() // restore first-commit transitions
    owners[1].input.value = 'A long example note'
    for (let i = 0; i < 100; i++) owners[1].autosize.schedule()
    assert.equal(frames.size, 1)
    await flush()
    assert.equal(owners[1].input.style.height, '100px')
    resolveFonts()
    await Promise.resolve()
    assert.equal(frames.size, 1, 'Font completion schedules one shared frame')
    owners[1].scope.dispose()
    assert.equal(frames.size, 1, 'Removing an owner cannot cancel surviving owners')
    await flush()
    for (const owner of owners) owner.scope.dispose()
    assert.equal(frames.size, 0)
    assert.equal(listeners.size, 0)
    assert.equal(removed, 299) // retired before the first pass: no mirror was created
    ownerDocument.fonts.status = 'loaded'
    let width = 0, settled = false
    const hiddenScope = createResourceScope()
    const hiddenInput = { ownerDocument, isConnected: true, value: 'short', style: {}, get clientWidth() { readLayout(); return width } }
    const hidden = createTextareaAutosize({ querySelector: () => ({ appendChild() {} }) }, hiddenInput, { shouldAutosize: true, initialHeightRem: 2.25 }, hiddenScope)
    const hiddenReady = hidden.initialize().then(result => { settled = true; return result })
    await flush()
    assert.equal(settled, false, 'A zero-width owner is not ready merely because its empty queue pass finished')
    width = 300
    hidden.schedule(true)
    await flush()
    assert.equal(await hiddenReady, true)
    assert.equal(hiddenInput.style.height, '36px')
    hiddenScope.dispose()
    assert.equal(frames.size, 0)
    assert.equal(listeners.size, 0)
    let nativeMirrors = 0
    const nativeDocument = {
        ...ownerDocument,
        defaultView: { ...ownerDocument.defaultView, getComputedStyle: () => ({ fieldSizing: 'content', writingMode: 'horizontal-tb', fontSize: '16px', maxHeight: '100px', paddingTop: '0px', paddingBottom: '0px', borderTopWidth: '0px', borderBottomWidth: '0px' }) },
        createElement() { nativeMirrors++; return ownerDocument.createElement() },
    }
    const nativeScope = createResourceScope()
    const nativeInput = { ownerDocument: nativeDocument, isConnected: true, value: 'Three native lines', style: {}, clientWidth: 300, clientHeight: 72 }
    const native = createTextareaAutosize({ querySelector: () => ({ appendChild() {} }) }, nativeInput, { shouldAutosize: true, initialHeightRem: 2.25 }, nativeScope)
    const nativeReady = native.initialize()
    await flush()
    assert.equal(await nativeReady, true)
    assert.equal(nativeMirrors, 0)
    assert.equal(nativeInput.style.height, undefined, 'Native initial height must stay untouched before the first edit')
    nativeInput.value = 'Longer content after the first edit'
    native.schedule()
    assert.equal(nativeInput.style.height, '72px', 'The first edit freezes the previously painted height for animation')
    await flush()
    assert.equal(nativeMirrors, 1)
    assert.equal(nativeInput.style.height, '100px')
    nativeScope.dispose()
    assert.equal(frames.size, 0)
    assert.equal(listeners.size, 0)
    console.log('PASS: 300 independent textarea owners; phased layout, real readiness, min/max height, coalesced edits/fonts, cancellation, failure isolation and complete disposal.')
} finally {
    globalThis.requestAnimationFrame = originalFrame
    globalThis.cancelAnimationFrame = originalCancel
}
