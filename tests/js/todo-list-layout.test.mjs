import test from 'node:test'
import assert from 'node:assert/strict'
import {
    queueTodoLayout,
    todoFontsReady,
} from '../../resources/js/components/todo-list-field/rendering/layout-queue.js'
import { createTodoChrome } from '../../resources/js/components/todo-list-field/rendering/chrome.js'
import { syncOverlayScrollbar } from '../../resources/js/core/overlay-scrollbar.js'

function documentFixture() {
    const frames = new Map()
    let seq = 0,
        fontReads = 0
    const document = {
        defaultView: {
            requestAnimationFrame(fn) {
                frames.set(++seq, fn)
                return seq
            },
            cancelAnimationFrame(id) {
                frames.delete(id)
            },
        },
        fonts: {
            get ready() {
                fontReads++
                return Promise.resolve()
            },
        },
    }
    return {
        document,
        frames,
        get fontReads() {
            return fontReads
        },
        flush() {
            const current = [...frames.values()]
            frames.clear()
            for (const fn of current) fn()
        },
        root() {
            return { ownerDocument: document, isConnected: true }
        },
    }
}

test('all Todo instances read geometry before any instance writes it', () => {
    const f = documentFixture(),
        events = []
    for (let i = 0; i < 18; i++)
        queueTodoLayout(f.root(), () => {
            events.push(`read:${i}`)
            return () => events.push(`write:${i}`)
        })
    assert.equal(f.frames.size, 1)
    assert.deepEqual(events, [])
    f.flush()
    assert.deepEqual(events, [
        ...Array.from({ length: 18 }, (_, i) => `read:${i}`),
        ...Array.from({ length: 18 }, (_, i) => `write:${i}`),
    ])
})

test('layout jobs are replaced per owner and cancellation cannot remove a newer job', () => {
    const f = documentFixture(),
        root = f.root(),
        events = []
    const cancel = queueTodoLayout(root, () => events.push('obsolete'))
    queueTodoLayout(root, () => {
        events.push('latest')
        return () => events.push('write')
    })
    cancel()
    f.flush()
    assert.deepEqual(events, ['latest', 'write'])
    const dispose = queueTodoLayout(root, () => events.push('disposed'))
    dispose()
    assert.equal(f.frames.size, 0)
    queueTodoLayout(root, () => events.push('detached'))
    root.isConnected = false
    f.flush()
    assert.deepEqual(events, ['latest', 'write'])
})

test('font readiness does not synchronously flush layout once for every Todo instance', () => {
    const f = documentFixture()
    const promises = Array.from({ length: 18 }, () => todoFontsReady(f.root()))
    assert.equal(f.fontReads, 1)
    assert.ok(promises.every((promise) => promise === promises[0]))
})

test('page actions retain their field controls when Alpine $el points at a child button', () => {
    const nodes = {
        '[data-todo-page-label]': { textContent: '1 / 8' },
        '[data-todo-load-status]': { textContent: '', hidden: true },
        '[data-todo-retry]': { hidden: true },
    }
    let rootQueries = 0
    const chrome = createTodoChrome({
        querySelector(selector) {
            rootQueries++
            return nodes[selector]
        },
    })
    const child = {
        querySelector() {
            throw Error('Actions must not query the button as the field root')
        },
    }
    const runtime = {
        $el: child,
        page: 2,
        pages: 8,
        labels: {},
        remoteLoader: null,
        loadingMore: false,
        loadError: null,
    }
    chrome.update(runtime)
    assert.equal(nodes['[data-todo-page-label]'].textContent, '2 / 8')
    runtime.loadError = 'network'
    chrome.update(runtime)
    assert.equal(nodes['[data-todo-retry]'].hidden, false)
    assert.equal(nodes['[data-todo-load-status]'].hidden, false)
    assert.equal(rootQueries, 3)
    createTodoChrome({
        querySelector() {
            return null
        },
    }).update(runtime)
})

test('scrollbar writes use the measured snapshot without another forced layout', () => {
    const cannotRead = () => {
        throw Error('DOM geometry read during write phase')
    }
    const track = { dataset: {} }
    Object.defineProperty(track, 'clientHeight', { get: cannotRead })
    const thumb = { style: {}, parentElement: track },
        scroller = {}
    for (const key of ['scrollTop', 'scrollHeight', 'clientHeight'])
        Object.defineProperty(scroller, key, { get: cannotRead })
    const result = syncOverlayScrollbar(scroller, thumb, 24, {
        scrollTop: 100,
        scrollHeight: 600,
        clientHeight: 200,
        trackHeight: 200,
    })
    assert.equal(result.visible, true)
    assert.ok(Math.abs(result.thumbHeight - 200 / 3) < 1e-9)
    assert.equal(track.dataset.visible, 'true')
})

test('a failing owner does not block the remaining layout readers or writers', () => {
    const f = documentFixture(),
        errors = [],
        events = []
    queueTodoLayout(
        f.root(),
        () => {
            throw Error('read failed')
        },
        (error) => errors.push(error.message),
    )
    queueTodoLayout(
        f.root(),
        () => () => {
            throw Error('write failed')
        },
        (error) => errors.push(error.message),
    )
    queueTodoLayout(f.root(), () => {
        events.push('read')
        return () => events.push('write')
    })
    f.flush()
    assert.deepEqual(errors, ['read failed', 'write failed'])
    assert.deepEqual(events, ['read', 'write'])
})

test('connected row reserve is bounded and resets every recycled owner', async () => {
    const { createTodoRowReserve } = await import('../../resources/js/components/todo-list-field/rendering/recycling.js')
    let resets = 0, removals = 0
    const entry = () => ({ group: { hidden: false, removeAttribute() {}, remove() { removals++ } } })
    const reserve = createTodoRowReserve({ limit: 2, reset() { resets++ } })
    const a = entry(), b = entry(), c = entry()
    reserve.keep(a)
    reserve.keep(b)
    reserve.keep(c)
    assert.equal(resets, 3)
    assert.equal(removals, 1)
    assert.equal(a.group.hidden, true)
    assert.equal(reserve.take(), b)
    reserve.clear()
    assert.equal(removals, 2)
    assert.equal(reserve.take(), null)
})

test('Todo can own scrollbar scroll scheduling without changing other consumers', async () => {
    const { bindOverlayScrollbar } = await import('../../resources/js/core/overlay-scrollbar.js')
    const target = () => {
        const listeners = new Map()
        return { listeners, addEventListener(name, fn) { listeners.set(name, fn) }, removeEventListener(name, fn) { if (listeners.get(name) === fn) listeners.delete(name) } }
    }
    const win = target(), scroller = target(), track = { ...target(), dataset: {}, ownerDocument: { defaultView: win } }, thumb = { style: {}, parentElement: track }
    const controlled = bindOverlayScrollbar(scroller, track, thumb, { manageScroll: false })
    assert.equal(scroller.listeners.has('scroll'), false)
    assert.equal(track.listeners.has('pointerdown'), true)
    assert.equal(bindOverlayScrollbar(scroller, track, thumb, { manageScroll: false }), controlled)
    const standard = bindOverlayScrollbar(scroller, track, thumb)
    assert.notEqual(standard, controlled)
    assert.equal(scroller.listeners.has('scroll'), true)
    standard()
    controlled()
    assert.equal(scroller.listeners.size, 0)
    assert.equal(track.listeners.size, 0)
})

test('scrollbar position scrubs a paused native transform without style writes', async () => {
    const { createTodoScrollbarPosition } = await import('../../resources/js/components/todo-list-field/rendering/scrollbar-position.js')
    let paused = false, cancelled = false, writes = 0, time = null
    const animation = { pause() { paused = true }, cancel() { cancelled = true }, set currentTime(value) { time = value; writes++ } }
    const position = createTodoScrollbarPosition({ animate() { return animation }, style: new Proxy({}, { set() { throw Error('Inline style invalidates ancestor selectors') } }) })
    assert.equal(paused, true)
    position.set(12.5)
    position.set(12.5)
    assert.equal(time, 12.5)
    assert.equal(writes, 2)
    position.dispose()
    assert.equal(cancelled, true)
    const thumb = { style: {} }
    const fallback = createTodoScrollbarPosition(thumb)
    fallback.set(30)
    assert.equal(thumb.style.transform, 'translateY(30px)')
    fallback.dispose()
})
