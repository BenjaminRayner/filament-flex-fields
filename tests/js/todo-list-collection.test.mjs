import test from 'node:test'
import assert from 'node:assert/strict'
import { createTodoWindow } from '../../resources/js/components/todo-list-field/collection/window.js'
import { createTodoPager } from '../../resources/js/components/todo-list-field/collection/remote.js'
import { projectTodoItems } from '../../resources/js/components/todo-list-field/collection/projection.js'
import { createTodoActions } from '../../resources/js/components/todo-list-field/actions.js'
import { createTodoPresentation } from '../../resources/js/components/todo-list-field/presentation.js'

function viewportFixture() {
    const observers = new Set(),
        listeners = new Map(),
        timers = new Map(),
        frames = new Map()
    let seq = 0
    const win = {
        setTimeout(fn) {
            timers.set(++seq, fn)
            return seq
        },
        clearTimeout(id) {
            timers.delete(id)
        },
        requestAnimationFrame(fn) {
            frames.set(++seq, fn)
            return seq
        },
        cancelAnimationFrame(id) {
            frames.delete(id)
        },
        ResizeObserver: class {
            constructor(fn) {
                this.fn = fn
                this.targets = new Set()
                observers.add(this)
            }
            observe(el) {
                this.targets.add(el)
            }
            unobserve(el) {
                this.targets.delete(el)
            }
            disconnect() {
                this.targets.clear()
                observers.delete(this)
            }
        },
    }
    const viewport = {
        ownerDocument: { defaultView: win },
        clientWidth: 400,
        clientHeight: 200,
        offsetWidth: 400,
        offsetHeight: 200,
        scrollHeight: 100000,
        scrollTop: 0,
        addEventListener(name, fn) {
            listeners.set(name, fn)
        },
        removeEventListener(name, fn) {
            if (listeners.get(name) === fn) listeners.delete(name)
        },
        scrollTo({ top }) {
            if (top !== this.scrollTop) {
                this.scrollTop = top
                listeners.get('scroll')?.()
            }
        },
        getBoundingClientRect() {
            return { width: this.clientWidth, height: this.clientHeight }
        },
    }
    return {
        viewport,
        win,
        listeners,
        timers,
        observers,
        scroll(offset) {
            viewport.scrollTop = offset
            listeners.get('scroll')?.()
        },
        measure(window, node, height) {
            window.measure(node)
            for (const observer of observers)
                if (observer.targets.has(node))
                    observer.fn([{ target: node, borderBoxSize: [{ blockSize: height }] }])
        },
        resize() {
            for (const observer of observers)
                if (observer.targets.has(viewport))
                    observer.fn([
                        {
                            borderBoxSize: [
                                {
                                    inlineSize: viewport.offsetWidth,
                                    blockSize: viewport.offsetHeight,
                                },
                            ],
                        },
                    ])
        },
    }
}

test('persistent TanStack window limits DOM demand for 10000 roots and measures natural height', () => {
    const f = viewportFixture(),
        window = createTodoWindow({
            viewport: f.viewport,
            onChange() {},
            estimateSize: 50,
        })
    window.mount()
    window.setItems(
        Array.from({ length: 10000 }, (_, id) => ({ id: String(id) })),
    )
    const before = window.read()
    assert.ok(before.rows.length < 20)
    assert.equal(before.rows[0].item.id, '0')
    f.measure(window, { isConnected: true, getAttribute: () => '0' }, 150)
    assert.equal(window.read().totalSize, before.totalSize + 100)
    f.scroll(20000)
    assert.ok(Number(window.read().rows[0].item.id) > 300)
    window.dispose()
    window.dispose()
    assert.equal(f.listeners.size, 0)
    assert.equal(f.timers.size, 0)
    for (const observer of f.observers) assert.equal(observer.targets.size, 0)
})

test('keyed measurements survive collection append and filtered count changes', () => {
    const f = viewportFixture(),
        window = createTodoWindow({
            viewport: f.viewport,
            onChange() {},
            estimateSize: 50,
        })
    const items = Array.from({ length: 20 }, (_, id) => ({ id: String(id) }))
    window.mount()
    window.setItems(items)
    window.read()
    f.measure(window, { isConnected: true, getAttribute: () => '0' }, 120)
    window.setItems([...items, { id: 'new' }])
    assert.equal(window.read().totalSize, 21 * 50 + 20 * 4 + 70)
    window.setItems([items[0]])
    assert.equal(window.read().totalSize, 120)
    window.dispose()
})

test('rapid scroll jumps publish a covering window synchronously without waiting for a frame', () => {
    const fixture = viewportFixture()
    const notifications = []
    const window = createTodoWindow({
        viewport: fixture.viewport,
        estimateSize: 50,
        onChange: (synchronous) => notifications.push(synchronous),
    })
    window.mount()
    window.setItems(Array.from({ length: 10000 }, (_, id) => ({ id: String(id) })))
    window.read()
    for (let index = 0; index < 100; index++) {
        const height = 40 + (index % 5) * 35
        fixture.measure(window, { isConnected: true, getAttribute: () => String(index) }, height)
    }
    for (const offset of [2000, 50000, 400, 250000, 8000, 0, 150000]) {
        notifications.length = 0
        fixture.scroll(offset)
        const result = window.read()
        assert.equal(notifications[0], true)
        assert.ok(result.rows[0].start <= offset)
        assert.ok(result.rows.at(-1).end >= offset + fixture.viewport.clientHeight)
        assert.ok(result.rows.length < 30)
        const metrics = window.metrics()
        assert.equal(metrics.scrollTop, offset)
        assert.equal(metrics.clientHeight, fixture.viewport.clientHeight)
        assert.equal(metrics.totalSize, result.totalSize)
    }
    window.dispose()
    assert.equal(fixture.listeners.size, 0)
    assert.equal(fixture.timers.size, 0)
})

test('virtual scrollbar snapshots do not force viewport layout after DOM writes', () => {
    const fixture = viewportFixture()
    const window = createTodoWindow({ viewport: fixture.viewport, onChange() {}, estimateSize: 50 })
    window.mount()
    window.setItems(Array.from({ length: 100 }, (_, id) => ({ id: String(id) })))
    const result = window.read()
    for (const key of ['scrollTop', 'scrollHeight', 'clientHeight'])
        Object.defineProperty(fixture.viewport, key, { get() { throw Error('Unexpected layout read') } })
    assert.deepEqual(window.metrics(), { scrollTop: 0, clientHeight: 200, totalSize: result.totalSize })
    window.dispose()
})

test('separate local pages retain all edits and search resets the clamped page', () => {
    const items = Array.from({ length: 45 }, (_, id) => ({
        id: String(id),
        label: `Task ${id}`,
        done: id === 3,
    }))
    const page = projectTodoItems(items, { page: 3, pageSize: 10 })
    assert.equal(page.items[0].id, '20')
    assert.equal(page.pages, 5)
    const filtered = projectTodoItems(items, {
        page: 3,
        pageSize: 10,
        query: 'Task 30',
    })
    assert.equal(filtered.page, 1)
    assert.equal(items[3].done, true)
    const more = projectTodoItems(items, { infinite: true, visibleCount: 8 })
    assert.equal(more.items.length, 8)
    assert.equal(more.hasMore, true)
})

test('remote requests discard out-of-order results, preserve user edits and expose explicit visible ids', async () => {
    const requests = [],
        committed = [],
        status = []
    let state = [{ id: 'a', label: 'A', done: true }]
    const pager = createTodoPager({
        load: (page, query, signal) =>
            new Promise((resolve) => requests.push({ resolve, signal })),
        commit: (value) => {
            committed.push(value)
            state = value.items
        },
        onStatus: (value) => status.push(value),
        pageSize: 2,
    })
    const old = pager.request(2, 'old', () => state, false),
        fresh = pager.request(1, 'new', () => state, false)
    assert.equal(requests[0].signal.aborted, true)
    requests[1].resolve({
        items: [
            { id: 'a', label: 'A', done: false },
            { id: 'b', label: 'B' },
        ],
        hasMore: false,
        total: 2,
    })
    await fresh
    requests[0].resolve({
        items: [{ id: 'stale', label: 'Stale' }],
        hasMore: true,
    })
    await old
    assert.equal(committed.length, 1)
    assert.equal(state[0].done, true)
    assert.deepEqual(committed[0].visibleIds, ['a', 'b'])
    assert.equal(committed[0].hasMore, false)
    assert.equal(status.at(-1).loading, false)
    pager.dispose()
})

test('remote errors allow retry and disposed requests never commit', async () => {
    let calls = 0,
        commits = 0,
        lastStatus
    const pager = createTodoPager({
        load: async () => {
            if (++calls === 1) throw Error('offline')
            return []
        },
        commit() {
            commits++
        },
        onStatus: (value) => {
            lastStatus = value
        },
    })
    assert.equal(await pager.request(1, '', () => []), null)
    assert.equal(lastStatus.loading, false)
    await pager.request(1, '', () => [])
    assert.equal(commits, 1)
    pager.dispose()
    await pager.request(2, '', () => [])
    assert.equal(calls, 2)
})

test('rejected reorder never commits and successful reorder preserves concurrent edits and locked slots', async () => {
    let resolve
    const runtime = {
        ...createTodoPresentation(),
        ...createTodoActions(),
        state: [
            { id: 'a', label: 'A' },
            { id: 'locked', label: 'Locked', disabled: true },
            { id: 'b', label: 'B' },
        ],
        disabled: false,
        _disposed: false,
        reorderable: true,
        reorderUsingEnabled: true,
        search: '',
        refresh() {},
        callSchemaMethod: () =>
            new Promise((done) => {
                resolve = done
            }),
    }
    const rejected = runtime.reorderItems({ oldIndex: 0, newIndex: 2 })
    resolve(false)
    await rejected
    assert.equal(runtime.state[0].id, 'a')
    const accepted = runtime.reorderItems({ oldIndex: 0, newIndex: 2 })
    runtime.state[0] = { ...runtime.state[0], done: true }
    resolve(true)
    await accepted
    assert.deepEqual(
        runtime.state.map((item) => item.id),
        ['b', 'locked', 'a'],
    )
    assert.equal(runtime.state[2].done, true)
})

test('Todo configuration owns every assignment before Alpine scope merging', async () => {
    const { createTodoRuntime } =
        await import('../../resources/js/components/todo-list-field/runtime/controller.js')
    const first = createTodoRuntime({ state: [] }),
        second = createTodoRuntime({ state: [] }),
        ancestor = {}
    const merged = new Proxy(first, {
        set(target, key, value) {
            if (Object.hasOwn(target, key)) target[key] = value
            else ancestor[key] = value
            return true
        },
    })
    Object.assign(merged, {
        paginated: true,
        pageSize: 8,
        virtualizing: true,
        remoteLoader: 'loadTasks',
        undoEvent: 'owner-a',
        _undoCleanup: () => {},
        _scrollbarGeometry: 'geometry-a',
    })
    assert.deepEqual(ancestor, {})
    assert.equal(first.paginated, true)
    assert.equal(second.paginated, false)
    assert.equal(second.pageSize, null)
    assert.equal(second.remoteLoader, null)
    assert.equal(second._undoCleanup, null)
    assert.notEqual(first.settledIds, second.settledIds)
})

test('repeated tab indicator measurements do not write classes or styles again', async () => {
    const { paintSegmentIndicator } =
        await import('../../resources/js/core/segment-indicator.js')
    const classes = new Set()
    let writes = 0
    const track = {
        offsetWidth: 300,
        classList: {
            contains: (value) => classes.has(value),
            add(value) {
                classes.add(value)
                writes++
            },
            remove(value) {
                classes.delete(value)
                writes++
            },
        },
    }
    const indicator = {
        style: new Proxy(
            {},
            {
                set(target, key, value) {
                    writes++
                    target[key] = value
                    return true
                },
            },
        ),
    }
    const selected = {
        offsetWidth: 120,
        offsetHeight: 36,
        offsetLeft: 20,
        offsetTop: 0,
    }
    paintSegmentIndicator(track, indicator, selected)
    const initial = writes
    for (let i = 0; i < 100; i++)
        paintSegmentIndicator(track, indicator, selected)
    assert.equal(writes, initial)
    paintSegmentIndicator(track, indicator, null)
    const hidden = writes
    for (let i = 0; i < 100; i++) paintSegmentIndicator(track, indicator, null)
    assert.equal(writes, hidden)
})

test('invalid remote rows fail without committing and permit a subsequent retry', async () => {
    let payload = [{ id: 'bad', label: '', done: 'yes' }],
        commits = 0,
        error = null
    const pager = createTodoPager({
        load: async () => payload,
        commit() {
            commits++
        },
        onStatus(value) {
            if (value.error) error = value.error
        },
    })
    await pager.request(1, '', () => [])
    assert.equal(commits, 0)
    assert.equal(error, 'remote_load_failed')
    payload = [{ id: 'valid', label: 'Valid' }]
    await pager.request(1, '', () => [])
    assert.equal(commits, 1)
    pager.dispose()
})

test('row cleanup cancels only owned work without enumerating browser animations', async () => {
    const {cancelRowMotion} = await import('../../resources/js/components/todo-list-field/motion.js')
    cancelRowMotion({getAnimations(){throw Error('Unexpected style flush')}})
})

test('strike measurements batch all geometry reads and skip unchanged SVG writes', async () => {
    const {layoutTodoStrikes} = await import('../../resources/js/components/todo-list-field/rendering/strike-layout.js')
    const operations = []
    const wraps = [0,1,2].map(index => {
        const values={}
        const strike={hidden:false,style:new Proxy(values,{set(target,key,value){operations.push('write');target[key]=value;return true}})}
        const host={querySelector:()=>strike,querySelectorAll:()=>[strike]}
        const label={index}
        return {getBoundingClientRect(){operations.push('read');return {top:index*30,left:0}},querySelector(selector){return selector.includes('__label')?label:host}}
    })
    let selected
    const root={querySelectorAll:()=>wraps,ownerDocument:{createRange(){return {selectNodeContents(label){selected=label},getClientRects(){operations.push('read');return [{top:selected.index*30,left:0,width:100,height:20,right:100,bottom:selected.index*30+20}]}}}}}
    layoutTodoStrikes(root)
    assert.equal(operations.slice(0,6).every(value=>value==='read'),true)
    assert.equal(operations.slice(6).every(value=>value==='write'),true)
    operations.length=0;layoutTodoStrikes(root)
    assert.equal(operations.includes('write'),false)
})


test('edit shows busy state immediately, prevents duplicate mounts and recovers after failure', async () => {
    const { createTodoState } = await import('../../resources/js/components/todo-list-field/runtime/state.js')
    let finish, mounts = 0, errors = 0
    const runtime = {
        ...createTodoState([{ id: 'a', label: 'A' }, { id: 'b', label: 'B' }]), ...createTodoPresentation(), ...createTodoActions(),
        componentKey: 'form.tasks', allowEdit: true, editableMode: 'all', refresh() {},
        $dispatch() { errors++ },
        $wire: { mountAction: () => { mounts++; return new Promise((resolve, reject) => { finish = reject }) } },
    }
    const opening = runtime.editItem('a')
    assert.equal(runtime.actionBusy.a, 'edit')
    await runtime.editItem('a')
    await runtime.editItem('b')
    assert.equal(mounts, 1)
    finish(Error('offline'))
    await opening
    assert.equal(runtime.actionBusy.a, undefined)
    assert.equal(errors, 1)
    runtime.$wire.mountAction = async () => { mounts++ }
    await runtime.editItem('a')
    assert.equal(mounts, 2)
    assert.equal(runtime.state.length, 2)
})

test('confirmed delete only mounts the modal; accepted sync removes the task once', async () => {
    const { createTodoState } = await import('../../resources/js/components/todo-list-field/runtime/state.js')
    const calls = []
    const runtime = {
        ...createTodoState([{ id: 'a', label: 'A' }, { id: 'b', label: 'B' }]),
        ...createTodoPresentation(), ...createTodoActions(),
        componentKey: 'form.tasks', allowDelete: true, deletableMode: 'all', deleteConfirmation: true,
        reducedMotion: true, refresh() {}, play() {},
        $wire: { mountAction: async (...args) => calls.push(args) },
        callSchemaMethod() { throw Error('Should not invoke callback before confirmation') },
    }
    await runtime.removeItem('a')
    assert.equal(calls[0][0], 'confirmDeleteTodoItem')
    assert.equal(runtime.state.length, 2)
    runtime.applyServerDeletedItem('a')
    assert.deepEqual(runtime.state.map(item => item.id), ['b'])
    runtime._disposed = true
    runtime.applyServerDeletedItem('b')
    assert.equal(runtime.state.length, 1)
})

test('measurement batch preserves the visible anchor with one correction after geometry commit', () => {
    const f = viewportFixture()
    const notifications = []
    const window = createTodoWindow({ viewport: f.viewport, estimateSize: 50, onChange: (...args) => notifications.push(args) })
    window.mount()
    window.setItems(Array.from({ length: 100 }, (_, id) => ({ id: String(id) })))
    window.read()
    f.scroll(2000)
    const nodes = [0, 1, 2].map(index => ({ isConnected: true, getAttribute: () => String(index) }))
    for (const node of nodes) window.measure(node)
    const observer = [...f.observers].find(value => value.targets.has(nodes[0]))
    let corrections = 0
    const scrollTo = f.viewport.scrollTo.bind(f.viewport)
    f.viewport.scrollTo = options => { corrections++; scrollTo(options) }
    notifications.length = 0
    observer.fn(nodes.map(target => ({ target, borderBoxSize: [{ blockSize: 100 }] })))
    assert.equal(f.viewport.scrollTop, 2000, 'measurement must not clamp against obsolete DOM geometry')
    assert.deepEqual(notifications, [[false, true]])
    window.read()
    window.commit()
    assert.equal(f.viewport.scrollTop, 2150)
    assert.equal(corrections, 1)
    window.commit()
    assert.equal(corrections, 1)
    const size = window.read().totalSize
    window.invalidateWidth()
    assert.equal(window.read().totalSize, size, 'resize retains geometry until replacements arrive')
    window.unmeasure(nodes[0])
    observer.fn([{ target: nodes[0], borderBoxSize: [{ blockSize: 500 }] }])
    assert.equal(window.read().totalSize, size, 'retired rows cannot update another window')
    window.dispose()
    observer.fn([{ target: nodes[1], borderBoxSize: [{ blockSize: 500 }] }])
    assert.equal(f.listeners.size, 0)
})

test('a newer native scroll supersedes a pending anchor correction', () => {
    const f = viewportFixture()
    const window = createTodoWindow({ viewport: f.viewport, estimateSize: 50, onChange() {} })
    window.mount()
    window.setItems(Array.from({ length: 100 }, (_, id) => ({ id: String(id) })))
    window.read()
    f.scroll(1000)
    f.measure(window, { isConnected: true, getAttribute: () => '0' }, 200)
    f.scroll(1500)
    window.read()
    window.commit()
    assert.equal(f.viewport.scrollTop, 1500)
    window.dispose()
})

test('small collections avoid window churn and subtasks count toward the DOM budget', () => {
    const f = viewportFixture()
    const window = createTodoWindow({ viewport: f.viewport, onChange() {}, estimateSize: 50 })
    window.mount()
    const items = Array.from({ length: 40 }, (_, id) => ({ id: String(id) }))
    window.setItems(items)
    const before = window.read().rows.map(row => row.key)
    assert.equal(before.length, 40)
    f.scroll(1000)
    assert.deepEqual(window.read().rows.map(row => row.key), before)
    items[0] = { ...items[0], children: Array.from({ length: 100 }, (_, id) => ({ id: `c${id}` })) }
    window.setItems(items)
    assert.ok(window.read().rows.length < 40)
    window.dispose()
})
