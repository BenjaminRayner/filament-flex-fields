import assert from 'node:assert/strict'
import { test } from 'node:test'
import { bindUploadReorder } from '../../resources/js/file-upload/presentation/reorder.js'
import { createMediaOwner } from '../../resources/js/media/lifecycle/owner.js'

function fixture() {
    const owner = createMediaOwner(), root = new EventTarget(), commits = [], errors = []
    const files = [{ id: 'a', pondId: 'native-a' }, { id: 'b', pondId: 'native-b' }]
    root.classList = { remove() {} }
    root.releasePointerCapture = () => {}
    const target = { closest(selector) {
        if (selector === '[data-fff-sort-handle]') return this
        if (selector === '[data-fff-upload-id]') return { dataset: { fffUploadId: 'a' } }
        return null
    } }
    const emit = key => {
        const event = new Event('keydown', { cancelable: true })
        Object.defineProperties(event, { key: { value: key }, target: { value: target } })
        root.dispatchEvent(event)
        return event
    }
    let failure = false
    bindUploadReorder({ owner, root, getFiles: () => files,
        move(id, index) { const current = files.findIndex(file => file.pondId === id); files.splice(index, 0, files.splice(current, 1)[0]) },
        async persist() { if (failure) throw new Error('save failed'); commits.push(files.map(file => file.id)) }, report: error => errors.push(error.message) })
    return { owner, files, commits, errors, emit, fail: () => { failure = true } }
}

test('keyboard sorting shares native ordering, saves only real moves and stops after owner disposal', async () => {
    const f = fixture()
    try {
        f.emit('ArrowLeft')
        assert.equal(f.commits.length, 0)
        assert.equal(f.emit('ArrowRight').defaultPrevented, true)
        assert.deepEqual(f.files.map(file => file.id), ['b', 'a'])
        assert.deepEqual(f.commits, [['b', 'a']])
        f.emit('ArrowRight')
        assert.equal(f.commits.length, 1)
        f.owner.dispose()
        assert.equal(f.emit('ArrowLeft').defaultPrevented, false)
        assert.deepEqual(f.files.map(file => file.id), ['b', 'a'])
    } finally { f.owner.dispose() }
})

test('failed order persistence reaches the owned error presentation', async () => {
    const f = fixture()
    try {
        f.fail()
        f.emit('ArrowRight')
        await new Promise(resolve => setImmediate(resolve))
        assert.deepEqual(f.errors, ['save failed'])
    } finally { f.owner.dispose() }
})

test('a plain press keeps native file activation and a completed drag suppresses its synthetic click', () => {
    const originalRaf = globalThis.requestAnimationFrame, originalCancel = globalThis.cancelAnimationFrame
    let scheduled, captureCount = 0, activationCount = 0
    globalThis.requestAnimationFrame = callback => { scheduled = callback; return 1 }
    globalThis.cancelAnimationFrame = () => { scheduled = null }
    const owner = createMediaOwner(), root = new EventTarget()
    const files = [{ id: 'a', pondId: 'native-a' }, { id: 'b', pondId: 'native-b' }]
    const cls = { add() {}, remove() {}, contains: () => false }
    const parent = { getBoundingClientRect: () => ({ left: 0, top: 0 }), appendChild() {} }
    const ghost = { attributes: [], style: {}, classList: cls, querySelectorAll: () => [], removeAttribute() {}, setAttribute() {}, remove() {} }
    const row = { dataset: { fffUploadId: 'a' }, classList: cls, parentElement: parent, offsetParent: parent,
        offsetLeft: 0, offsetTop: 0, offsetWidth: 100, offsetHeight: 80,
        getBoundingClientRect: () => ({ left: 0, top: 0, width: 100, height: 80 }), cloneNode: () => ghost }
    const destination = { ...row, dataset: { fffUploadId: 'b' }, offsetLeft: 120 }
    const target = { closest(selector) {
        if (selector === '[data-fff-sort-handle]') return this
        if (selector === '[data-fff-upload-id]') return row
        return null
    } }
    Object.assign(root, { classList: cls, contains: candidate => candidate === row,
        setPointerCapture: () => { captureCount++ }, releasePointerCapture() {}, querySelectorAll: () => [row, destination] })
    const emit = (type, patch = {}) => {
        const event = new Event(type, { cancelable: true })
        for (const [key, value] of Object.entries({ target, button: 0, pointerId: 1, clientX: 40, clientY: 30, ...patch })) {
            Object.defineProperty(event, key, { value })
        }
        root.dispatchEvent(event)
        return event
    }
    bindUploadReorder({ owner, root, getFiles: () => files,
        move(id, index) { const previous = files.findIndex(file => file.pondId === id); files.splice(index, 0, files.splice(previous, 1)[0]) },
        persist: async () => {}, report: assert.fail })
    root.addEventListener('click', () => { activationCount++ })
    try {
        emit('pointerdown'); emit('pointerup'); emit('click')
        assert.equal(captureCount, 0); assert.equal(activationCount, 1)
        emit('pointerdown'); emit('pointermove', { clientX: 170 }); scheduled()
        assert.equal(captureCount, 1)
        emit('pointerup', { clientX: 170 }); assert.equal(emit('click').defaultPrevented, true)
        assert.equal(activationCount, 1); assert.deepEqual(files.map(file => file.id), ['b', 'a'])
        emit('pointerdown'); emit('pointerup'); emit('click'); assert.equal(activationCount, 2)
    } finally {
        owner.dispose(); globalThis.requestAnimationFrame = originalRaf; globalThis.cancelAnimationFrame = originalCancel
    }
})
