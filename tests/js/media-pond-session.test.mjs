import assert from 'node:assert/strict'
import { EventEmitter } from 'node:events'
import { test } from 'node:test'
import { createMediaOwner } from '../../resources/js/media/lifecycle/owner.js'
import { createPondSession } from '../../resources/js/media/upload/pond-session.js'

function nativePond() {
    const events = new EventEmitter()
    const files = []
    let options = { beforeAddFile: async () => true }
    return {
        files, events,
        on: events.on.bind(events), off: events.off.bind(events),
        getFiles: () => files, get beforeAddFile() { return options.beforeAddFile },
        setOptions: update => { options = { ...options, ...update } },
        async addFile(file) {
            if (!await options.beforeAddFile(file)) throw new Error('rejected')
            const item = { id: 'new', file, status: 3, origin: 1 }
            files.push(item)
            events.emit('addfile', null, item)
            return item
        },
    }
}

test('pond waits for completed native initialization, not the first registration', async () => {
    const owner = createMediaOwner()
    const session = createPondSession({ owner })
    const pond = nativePond()
    const component = { isInitializing: true }
    let completed = false
    const waiting = session.whenReady().then(result => { completed = true; return result })
    session.register({ component, pond, ready: false })
    await Promise.resolve()
    assert.equal(completed, false)
    component.isInitializing = false
    session.register({ component, pond, ready: true })
    assert.equal((await waiting).pond, pond)
    owner.dispose()
    assert.equal(pond.events.eventNames().length, 0)
})

test('replacement releases old events and waits for the new native pond', async () => {
    const owner = createMediaOwner()
    const session = createPondSession({ owner })
    const first = nativePond(), second = nativePond()
    session.register({ component: {}, pond: first, ready: true })
    first.files.push({ id: 'first', status: 3 })
    const pending = session.whenProcessed('first')
    session.register({ component: {}, pond: second, ready: true })
    await assert.rejects(pending, { reason: 'upload_replaced' })
    assert.equal(first.events.eventNames().length, 0)
    owner.dispose()
    assert.equal(second.events.eventNames().length, 0)
})

test('processing completes from native events without copying or rewriting Livewire state', async () => {
    const owner = createMediaOwner()
    const session = createPondSession({ owner })
    const pond = nativePond()
    const component = { state: ['native-temp'], shouldUpdateState: true }
    session.register({ component, pond, ready: true })
    const waiting = session.add(new Blob(['bytes']))
    while (!pond.files.length) await Promise.resolve()
    await Promise.resolve()
    pond.files[0].status = 5
    pond.files[0].serverId = 'native-key'
    pond.events.emit('processfile', null, pond.files[0])
    assert.equal((await waiting).serverId, 'native-key')
    assert.deepEqual(component.state, ['native-temp'])
    assert.equal(component.shouldUpdateState, true)
    owner.dispose()
})

test('processing cancellation and processing failure are distinct and remove every waiter', async () => {
    for (const [event, status, reason] of [['processfileabort', 2, 'upload_cancelled'], ['processfile', 6, 'upload_failed']]) {
        const owner = createMediaOwner()
        const session = createPondSession({ owner })
        const pond = nativePond()
        pond.files.push({ id: 'pending', status: 3 })
        session.register({ component: {}, pond, ready: true })
        const pending = session.whenProcessed('pending')
        pond.files[0].status = status
        pond.events.emit(event, null, pond.files[0])
        await assert.rejects(pending, { reason })
        assert.equal(pond.events.listenerCount('processfileabort'), 1)
        owner.dispose()
        assert.equal(pond.events.eventNames().length, 0)
    }
})

test('replacement confirmation composes with native image editor admission and is restored on disposal', async () => {
    const owner = createMediaOwner()
    const pond = nativePond()
    let nativeChecks = 0, allowed = false
    const native = async () => { nativeChecks++; return false }
    pond.setOptions({ beforeAddFile: native })
    const session = createPondSession({ owner, beforeAdd: () => allowed })
    session.register({ component: {}, pond, ready: true })
    assert.equal(await pond.beforeAddFile({}), false)
    assert.equal(nativeChecks, 0)
    allowed = true
    assert.equal(await pond.beforeAddFile({}), false)
    assert.equal(nativeChecks, 1)
    owner.dispose()
    assert.equal(pond.beforeAddFile, native)
})

test('disposed owners reject pending readiness and new operations', async () => {
    const owner = createMediaOwner()
    const session = createPondSession({ owner })
    const waiting = session.whenReady()
    owner.dispose()
    await assert.rejects(waiting, { reason: 'owner_disposed' })
    await assert.rejects(session.add(new Blob(['bytes'])), { reason: 'owner_disposed' })
    assert.throws(() => session.register({ component: {}, pond: nativePond(), ready: true }), { reason: 'owner_disposed' })
})

test('manual processing is instance-scoped and restored without changing another pond', async () => {
    const owner = createMediaOwner(), another = nativePond(), pond = nativePond()
    Object.defineProperty(pond, 'instantUpload', { get: () => instant })
    let instant = true
    const setOptions = pond.setOptions
    pond.setOptions = update => { if ('instantUpload' in update) instant = update.instantUpload; setOptions(update) }
    const session = createPondSession({ owner, instantUpload: false })
    session.register({ component: {}, pond, ready: true })
    assert.equal(pond.instantUpload, false)
    assert.equal(session.pending(), false)
    pond.files.push({ id: 'manual', status: 2, origin: 1 })
    let processed = 0
    pond.processFile = async id => { processed++; assert.equal(id, 'manual'); pond.files[0].status = 5; pond.events.emit('processfile', null, pond.files[0]) }
    assert.equal(session.pending(), true)
    await session.processPending()
    assert.equal(processed, 1)
    assert.equal(session.pending(), false)
    assert.equal(another.files.length, 0)
    owner.dispose()
    assert.equal(pond.instantUpload, true)
})

test('manual mode preserves explicit import processing and rejects invalid files at submit', async () => {
    const owner = createMediaOwner(), pond = nativePond()
    Object.defineProperty(pond, 'instantUpload', { get: () => false })
    pond.processFile = async () => { pond.files[0].status = 5; pond.events.emit('processfile', null, pond.files[0]) }
    const session = createPondSession({ owner, instantUpload: false })
    session.register({ component: {}, pond, ready: true })
    assert.equal((await session.add(new Blob(['capture']))).status, 5)
    pond.files[0].status = 6
    await assert.rejects(session.processPending(), { reason: 'upload_rejected' })
    owner.dispose()
})

test('manual source upload cancellation terminates even if native processing promise does not', async () => {
    const owner = createMediaOwner(), pond = nativePond()
    Object.defineProperty(pond, 'instantUpload', { get: () => false })
    pond.processFile = () => new Promise(() => {})
    const session = createPondSession({ owner, instantUpload: false })
    session.register({ component: {}, pond, ready: true })
    const pending = session.add(new Blob(['capture']))
    while (!pond.files.length) await Promise.resolve()
    await new Promise(resolve => setImmediate(resolve))
    pond.files[0].status = 2
    pond.events.emit('processfileabort', pond.files[0])
    await assert.rejects(pending, { reason: 'upload_cancelled' })
    owner.dispose()
    assert.equal(pond.events.eventNames().length, 0)
})

test('submit waits for native loading before starting a manually selected file', async () => {
    const owner = createMediaOwner(), pond = nativePond()
    pond.files.push({ id: 'loading', origin: 1, status: 7 })
    let processes = 0
    pond.processFile = async () => { processes++; pond.files[0].status = 5; pond.events.emit('processfile', null, pond.files[0]) }
    const session = createPondSession({ owner, instantUpload: false })
    session.register({ component: {}, pond, ready: true })
    const pending = session.processPending()
    await Promise.resolve()
    assert.equal(processes, 0)
    pond.files[0].status = 2
    pond.events.emit('addfile', null, pond.files[0])
    await pending
    assert.equal(processes, 1)
    owner.dispose()
})

test('retry delegates processing and revert failures to their distinct native operations', async () => {
    const owner = createMediaOwner(), pond = nativePond()
    const calls = []
    pond.files.push({ id: 'failed', status: 6 })
    pond.processFile = async id => { calls.push(['process', id]); return 'processed' }
    pond.removeFile = async (id, options) => { calls.push(['remove', id, options]); return 'reverted' }
    const session = createPondSession({ owner })
    session.register({ component: {}, pond, ready: true })
    try {
        assert.equal(await session.retry('failed'), 'processed')
        pond.files[0].status = 10
        assert.equal(await session.retry('failed'), 'reverted')
        pond.files[0].status = 3
        await session.retry('failed') // A stale click cannot restart an active upload.
        await session.retry('removed')
        assert.deepEqual(calls, [['process', 'failed'], ['remove', 'failed', { revert: true }]])
    } finally { owner.dispose() }
    await assert.rejects(session.retry('failed'), { reason: 'owner_disposed' })
})

test('load retry selects only the matching item in its own native pond', async () => {
    const owner = createMediaOwner(), pond = nativePond()
    let retries = 0
    pond.files.push({ id: 'failed', status: 8 })
    const button = { disabled: false, click() { retries++ } }
    pond.element = { querySelectorAll: () => [
        { id: 'filepond--item-unrelated', querySelector() { throw new Error('Wrong item') } },
        { id: 'filepond--item-failed', querySelector(selector) { assert.equal(selector, '.filepond--action-retry-item-load'); return button } },
    ] }
    pond.processFile = () => { throw new Error('Loading failure must not start processing') }
    const session = createPondSession({ owner })
    session.register({ component: {}, pond, ready: true })
    try {
        await session.retry('failed')
        assert.equal(retries, 1)
        button.disabled = true
        await assert.rejects(session.retry('failed'), { reason: 'upload_rejected' })
        pond.element = null
        await assert.rejects(session.retry('failed'), { reason: 'upload_rejected' })
        assert.equal(retries, 1)
    } finally { owner.dispose() }
})

test('pause retains its native item, blocks submit and resumes through a fresh processing request', async () => {
    const owner = createMediaOwner(), pond = nativePond(), snapshots = []
    const item = { id: 'active', status: 3, origin: 1, async abortProcessing() { this.status = 2; pond.events.emit('processfileabort', this) } }
    pond.files.push(item)
    let processes = 0
    pond.processFile = async id => { assert.equal(id, 'active'); processes++; item.status = 3; pond.events.emit('processfilestart', item) }
    const session = createPondSession({ owner, onChange: snapshot => snapshots.push(new Set(snapshot.pausedIds)) })
    session.register({ component: {}, pond, ready: true })
    try {
        await session.pause('active')
        assert.equal(item.status, 2)
        assert.equal(pond.files[0], item)
        assert.equal(snapshots.at(-1).has('active'), true)
        await assert.rejects(session.processPending(), { reason: 'upload_paused' })
        await session.resume('active')
        assert.equal(processes, 1)
        assert.equal(snapshots.at(-1).size, 0)
        await session.resume('active')
        assert.equal(processes, 1)
    } finally { owner.dispose() }
})

test('transport-only sessions disable duplicate previews and simulated minimum duration with owned restoration', () => {
    const owner = createMediaOwner(), pond = nativePond()
    Object.assign(pond, { allowMinimumUploadDuration: true, allowImagePreview: true, allowVideoPreview: true, allowAudioPreview: true, itemInsertInterval: 75 })
    const set = pond.setOptions
    pond.setOptions = options => { set(options); Object.assign(pond, Object.fromEntries(Object.entries(options).filter(([key]) => key !== 'beforeAddFile'))) }
    const session = createPondSession({ owner, transportOnly: true })
    session.register({ component: {}, pond, ready: true })
    assert.equal(pond.allowMinimumUploadDuration, false)
    assert.equal(pond.allowImagePreview, false)
    assert.equal(pond.itemInsertInterval, 0)
    owner.dispose()
    assert.equal(pond.allowMinimumUploadDuration, true)
    assert.equal(pond.allowImagePreview, true)
    assert.equal(pond.itemInsertInterval, 75)
})

test('transport selection retains identical existing sources and native validation path without hidden browse animation', () => {
    const owner = createMediaOwner(), pond = nativePond()
    const existing = new Blob(['existing']), selected = new Blob(['selected'])
    pond.files.push({ id: 'existing', source: existing, status: 3 })
    pond.allowMultiple = true
    pond.itemInsertLocation = 'before'
    let assigned
    Object.defineProperty(pond, 'files', { set(value) { assigned = value } })
    const session = createPondSession({ owner, transportOnly: true })
    session.register({ component: {}, pond, ready: true })
    try {
        session.selectFiles([selected])
        assert.deepEqual(assigned, [selected, existing])
        assert.equal(pond.getFiles()[0].id, 'existing')
        assert.equal(pond.getFiles()[0].status, 3)
        pond.itemInsertLocation = 'after'
        session.selectFiles([selected])
        assert.deepEqual(assigned, [existing, selected])
    } finally { owner.dispose() }
})

test('global pause stops active and queued requests, gates new files and restores manual mode on resume', async () => {
    const owner = createMediaOwner(), pond = nativePond(), snapshots = [], resumed = []
    let instant = false
    Object.defineProperty(pond, 'instantUpload', { get: () => instant })
    const originalOptions = pond.setOptions
    pond.setOptions = options => { if ('instantUpload' in options) instant = options.instantUpload; originalOptions(options) }
    for (const [id, status] of [['active', 3], ['queued', 9], ['manual', 2]]) {
        const file = { id, status, origin: 1, async abortProcessing() { this.status = 2; pond.events.emit('processfileabort', this) } }
        pond.files.push(file)
    }
    pond.processFile = id => { resumed.push(id); pond.files.find(file => file.id === id).status = 9; return new Promise(() => {}) }
    const session = createPondSession({ owner, instantUpload: false, onChange: state => snapshots.push({ paused: [...state.pausedIds], queue: state.queuePaused }) })
    session.register({ component: {}, pond, ready: true })
    try {
        await session.pauseAll()
        assert.deepEqual(pond.files.map(file => file.status), [2, 2, 2])
        pond.files.push({ id: 'added-during-pause', status: 2, origin: 1 })
        pond.events.emit('addfile')
        assert.equal(snapshots.at(-1).queue, true)
        assert.ok(snapshots.at(-1).paused.includes('added-during-pause'))
        await assert.rejects(session.processPending(), { reason: 'upload_paused' })
        await assert.rejects(session.resume('active'), { reason: 'upload_paused' })
        await session.resumeAll()
        assert.deepEqual(resumed, ['active', 'queued', 'manual', 'added-during-pause'])
        assert.equal(instant, false)
        assert.equal(snapshots.at(-1).queue, false)
    } finally { owner.dispose() }
})
