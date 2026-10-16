import test from 'node:test'
import assert from 'node:assert/strict'
import { planTodoOrder, nearestTodoSlot } from '../../resources/js/components/todo-list-field/reordering/order.js'
import { bindTodoReorder } from '../../resources/js/components/todo-list-field/reordering/pointer.js'
import { createTodoActions } from '../../resources/js/components/todo-list-field/actions.js'
import { createTodoState } from '../../resources/js/components/todo-list-field/runtime/state.js'

const seed = () => [
    { id: 'a', label: 'A' }, { id: 'locked', label: 'Locked', disabled: true },
    { id: 'b', label: 'B' }, { id: 'disabled', label: 'Disabled', disabled: true },
    { id: 'c', label: 'C' },
]

test('locked slots are immutable and keyboard targets skip disabled rows in both directions', () => {
    const items = seed()
    assert.deepEqual(planTodoOrder(items, 0, 1).map(item => item.id), ['b', 'locked', 'a', 'disabled', 'c'])
    assert.deepEqual(planTodoOrder(items, 4, 3).map(item => item.id), ['a', 'locked', 'c', 'disabled', 'b'])
    assert.deepEqual(planTodoOrder(items, 0, 4).map(item => item.id), ['b', 'locked', 'c', 'disabled', 'a'])
    assert.equal(planTodoOrder(items, 1, 4), null)
    assert.equal(planTodoOrder(items, -1, 4), null)
    assert.equal(planTodoOrder(items, 0, 5), null)
    assert.deepEqual(items.map(item => item.id), ['a', 'locked', 'b', 'disabled', 'c'])
})

test('drop targeting uses valid measured centers and clamps to either end', () => {
    const slots = seed().map((item, index) => ({ ...item, top: index * 60, height: 50 }))
    assert.equal(nearestTodoSlot(slots, 90).id, 'b')
    assert.equal(nearestTodoSlot(slots, -500).id, 'a')
    assert.equal(nearestTodoSlot(slots, 500).id, 'c')
    assert.equal(nearestTodoSlot(slots.map(slot => ({ ...slot, disabled: true })), 10), null)
})

function fixture() {
    const listeners = [], cleanups = [], frames = new Map()
    let sequence = 0
    class Node {
        constructor(id, top = 0) {
            this.dataset = { todoGroupId: id, todoId: id }
            this.top = top
            this.style = {}
            this.attrs = new Map()
            const classes = new Set()
            this.classList = { add: (...values) => values.forEach(v => classes.add(v)), remove: (...values) => values.forEach(v => classes.delete(v)), contains: v => classes.has(v) }
        }
        getBoundingClientRect() { return { top: this.top, left: 20, width: 400, height: 50 } }
        setAttribute(key, value) { this.attrs.set(key, value) }
        removeAttribute(key) { this.attrs.delete(key) }
        querySelectorAll() { return [] }
        matches() { return false }
        cloneNode() { return new Node(this.dataset.todoId, this.top) }
        closest() { return this.group ?? this }
        focus() {}
        setPointerCapture(id) { this.capture = id }
        releasePointerCapture() { this.capture = null }
        remove() { root.children = root.children.filter(child => child !== this) }
    }
    const groups = seed().map((item, index) => new Node(item.id, index * 60))
    const win = {}, doc = { defaultView: win, createElement: () => new Node() }
    const root = new Node()
    root.ownerDocument = doc
    root.children = []
    root.querySelectorAll = () => groups
    root.contains = node => groups.includes(node)
    root.append = (...nodes) => root.children.push(...nodes)
    const handle = new Node('a')
    handle.group = groups[0]
    const svgPath = { closest: selector => selector === '[data-todo-drag]' ? handle : null }
    const scope = {
        listen: (target, type, handler) => listeners.push({ target, type, handler }),
        defer: fn => cleanups.push(fn),
    }
    const runtime = {
        ...createTodoState(seed()), ...createTodoActions(), reorderable: true,
        items() { return this.state }, findItemById(id) { return this.state.find(item => item.id === id) },
        refresh() {}, frame: fn => { const key = ++sequence; frames.set(key, fn); return key },
        cancelFrame: key => frames.delete(key),
    }
    const emit = (target, type, extra = {}) => {
        const event = { target: svgPath, button: 0, isPrimary: true, pointerId: 1, clientY: 25, preventDefault() {}, ...extra }
        return Promise.all(listeners.filter(listener => listener.target === target && listener.type === type).map(listener => listener.handler(event)))
    }
    const flush = () => { const pending = [...frames.values()]; frames.clear(); pending.forEach(fn => fn()) }
    bindTodoReorder(runtime, scope, root)
    return { root, win, doc, runtime, groups, handle, emit, flush, dispose: () => cleanups.forEach(fn => fn()) }
}

test('SVG handle produces an inert ghost and visible valid drop marker; acceptance cleans both', async () => {
    const f = fixture()
    let accept
    f.runtime.reorderUsingEnabled = true
    f.runtime.callSchemaMethod = () => new Promise(resolve => { accept = resolve })
    await f.emit(f.root, 'pointerdown')
    await f.emit(f.root, 'pointermove', { clientY: 265 })
    f.flush()
    assert.equal(f.root.children.length, 2)
    const [ghost, marker] = f.root.children
    assert.equal(ghost.attrs.get('aria-hidden'), 'true')
    assert.equal(ghost.attrs.has('inert'), true)
    assert.equal(marker.hidden, false)
    assert.match(marker.style.transform, /292px/)
    assert.equal(f.groups[0].classList.contains('is-drag-source'), true)
    const finishing = f.emit(f.root, 'pointerup', { clientY: 265 })
    assert.equal(f.root.children.length, 2)
    accept(true)
    await finishing
    assert.deepEqual(f.runtime.state.map(item => item.id), ['b', 'locked', 'c', 'disabled', 'a'])
    assert.equal(f.root.children.length, 0)
    assert.equal(f.handle.capture, null)
})

for (const reason of ['pointercancel', 'resize', 'dispose', 'escape']) {
    test(`${reason} removes previews and preserves the task order`, async () => {
        const f = fixture()
        await f.emit(f.root, 'pointerdown')
        await f.emit(f.root, 'pointermove', { clientY: 160 })
        f.flush()
        if (reason === 'dispose') f.dispose()
        else if (reason === 'resize') await f.emit(f.win, 'resize')
        else if (reason === 'escape') await f.emit(f.root, 'keydown', { key: 'Escape' })
        else await f.emit(f.root, reason)
        assert.equal(f.root.children.length, 0)
        assert.deepEqual(f.runtime.state.map(item => item.id), seed().map(item => item.id))
        assert.equal(f.handle.capture, null)
    })
}

test('failed server reorder removes the preview without modifying state', async () => {
    const f = fixture()
    f.runtime.reorderUsingEnabled = true
    f.runtime.callSchemaMethod = async () => false
    await f.emit(f.root, 'pointerdown')
    await f.emit(f.root, 'pointermove', { clientY: 265 })
    f.flush()
    await f.emit(f.root, 'pointerup', { clientY: 265 })
    assert.equal(f.root.children.length, 0)
    assert.deepEqual(f.runtime.state.map(item => item.id), seed().map(item => item.id))
})
