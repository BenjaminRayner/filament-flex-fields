import test from 'node:test'
import assert from 'node:assert/strict'
import { createPlayback } from '../../resources/js/audio/playback.js'
import { createResourceScope } from '../../resources/js/runtime/lifecycle/resource-scope.js'

function fixture(rtl = false) {
    const owner = createResourceScope(), frames = new Map(), styles = new Map()
    let serial = 0, reads = 0, plays = 0
    const win = Object.assign(new EventTarget(), {
        requestAnimationFrame(callback) { const id = ++serial; frames.set(id, callback); return id },
        cancelAnimationFrame(id) { frames.delete(id) },
        getComputedStyle: () => ({ direction: rtl ? 'rtl' : 'ltr' }),
    })
    const doc = Object.assign(new EventTarget(), { defaultView: win, hidden: false })
    const audio = Object.assign(new EventTarget(), {
        duration: 100, currentTime: 0, readyState: 4, paused: true, src: '/audio.wav',
        getAttribute() { return this.src }, setAttribute(name, value) { this.src = value }, removeAttribute() { this.src = '' }, load() {},
        play() { plays++; this.paused = false; this.dispatchEvent(new Event('play')); return Promise.resolve() },
        pause() { this.paused = true; this.dispatchEvent(new Event('pause')) },
    })
    const wave = Object.assign(new EventTarget(), {
        style: { setProperty: (key, value) => styles.set(key, value) }, classList: { toggle() {} },
        getBoundingClientRect() { reads++; return { left: 0, width: 100 } },
        setPointerCapture() {}, hasPointerCapture: () => false,
    })
    const vm = { readOnly: false, audioSrc: '/audio.wav', $refs: { audio, waveform: wave }, $el: { ownerDocument: doc }, ...createPlayback(owner) }
    vm.mountPlayback()
    return { vm, owner, audio, wave, win, doc, styles, frames, get plays() { return plays }, get reads() { return reads },
        step() { const pending = [...frames.values()]; frames.clear(); pending.forEach(callback => callback()) },
    }
}

test('audio is idle without RAF, uses one playing clock and disposes it when hidden or removed', async () => {
    const f = fixture()
    assert.equal(f.frames.size, 0)
    await f.vm.togglePlay(); assert.equal(f.frames.size, 1)
    f.audio.currentTime = 20; f.step(); assert.equal(f.styles.get('--fff-audio-progress'), '0.2')
    assert.equal(f.frames.size, 1)
    f.doc.hidden = true; f.doc.dispatchEvent(new Event('visibilitychange')); assert.equal(f.frames.size, 0)
    f.doc.hidden = false; f.doc.dispatchEvent(new Event('visibilitychange')); assert.equal(f.frames.size, 1)
    f.owner.dispose(); assert.equal(f.frames.size, 0); assert.equal(f.audio.paused, true); assert.equal(f.audio.src, '')
})

test('scrubbing caches geometry, filters foreign pointers and mirrors RTL', () => {
    for (const rtl of [false, true]) {
        const f = fixture(rtl)
        f.vm.onWaveformPointerDown({ button: 0, isPrimary: true, pointerId: 1, clientX: 25, preventDefault() {} })
        assert.equal(f.audio.currentTime, rtl ? 75 : 25)
        const move = new Event('pointermove'); Object.assign(move, { pointerId: 1, clientX: 50 }); f.win.dispatchEvent(move)
        assert.equal(f.audio.currentTime, 50); assert.equal(f.reads, 1)
        const foreign = new Event('pointermove'); Object.assign(foreign, { pointerId: 2, clientX: 100 }); f.win.dispatchEvent(foreign)
        assert.equal(f.audio.currentTime, 50)
        f.win.dispatchEvent(new Event('resize')); f.win.dispatchEvent(move); assert.equal(f.reads, 2)
        const up = new Event('pointerup'); Object.assign(up, { pointerId: 1 }); f.win.dispatchEvent(up)
        assert.equal(f.vm.seeking, false); f.owner.dispose()
    }
})

test('keyboard seeks are bounded and readonly blocks both play and seeking', async () => {
    const f = fixture(true)
    f.vm.onWaveformKeyDown({ key: 'End', preventDefault() {} }); assert.equal(f.audio.currentTime, 100)
    f.vm.onWaveformKeyDown({ key: 'Home', preventDefault() {} }); assert.equal(f.audio.currentTime, 0)
    f.vm.onWaveformKeyDown({ key: 'ArrowLeft', preventDefault() {} }); assert.equal(f.audio.currentTime, 5)
    f.vm.readOnly = true; await f.vm.togglePlay(); f.vm.seekTo(.9)
    assert.equal(f.plays, 0); assert.equal(f.audio.currentTime, 5); f.owner.dispose()
})

test('audio decoding has a bounded streaming fetch and releases resources on cancellation', async t => {
    const { loadAudioBuffer } = await import('../../resources/js/audio/load-buffer.js')
    const originalFetch = globalThis.fetch, OriginalContext = globalThis.AudioContext
    t.after(() => { globalThis.fetch = originalFetch; globalThis.AudioContext = OriginalContext })
    let cancelled = 0, decoded = 0, closed = 0
    globalThis.fetch = async () => ({ ok: true, headers: new Headers(), body: { getReader: () => ({
        read: async () => ({ done: false, value: new Uint8Array(5) }),
        cancel: async () => { cancelled++ }, releaseLock() {},
    }) } })
    globalThis.AudioContext = class { decodeAudioData() { decoded++; return Promise.resolve({ duration: 1 }) } close() { closed++; this.state = 'closed'; return Promise.resolve() } }
    await assert.rejects(loadAudioBuffer('/audio.wav', { maxBytes: 4 }), /audio_size_rejected/)
    assert.equal(decoded, 0); assert.equal(cancelled, 1)
    const signal = new AbortController(); signal.abort()
    await assert.rejects(loadAudioBuffer('/audio.wav', { signal: signal.signal }))
    globalThis.fetch = async () => new Response(new Uint8Array(4))
    globalThis.AudioContext = class { decodeAudioData() { decoded++; return new Promise(() => {}) } close() { closed++; this.state = 'closed'; return Promise.resolve() } }
    const decoding = new AbortController(), pending = loadAudioBuffer('/audio.wav', { signal: decoding.signal })
    await new Promise(resolve => setImmediate(resolve)); decoding.abort(); await assert.rejects(pending)
    assert.equal(decoded, 1); assert.equal(closed, 1)
})
