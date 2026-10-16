import test from 'node:test'
import assert from 'node:assert/strict'
import { createMediaOwner, createLivewireUpload, registerMediaSubmit } from '../../resources/js/components/media-os.js'

const flush = () => new Promise((resolve) => setImmediate(resolve))

class Form extends EventTarget {
    attributes = new Map()
    submitted = 0
    submitter = null
    getAttribute(name) { return this.attributes.get(name) ?? null }
    setAttribute(name, value) { this.attributes.set(name, value) }
    removeAttribute(name) { this.attributes.delete(name) }
    requestSubmit(submitter) {
        const event = new Event('submit', { cancelable: true })
        event.submitter = submitter
        this.dispatchEvent(event)
        if (!event.defaultPrevented) { this.submitted++; this.submitter = submitter }
    }
}

test('an owner replaces URLs, stops tracks and cancels readiness once', async () => {
    const urls = new Set()
    const oldCreate = URL.createObjectURL
    const oldRevoke = URL.revokeObjectURL
    let sequence = 0
    URL.createObjectURL = () => { const url = `blob:${++sequence}`; urls.add(url); return url }
    URL.revokeObjectURL = (url) => urls.delete(url)
    try {
        const owner = createMediaOwner()
        owner.objectUrl('preview', new Blob(['first']))
        owner.objectUrl('preview', new Blob(['second']))
        assert.equal(urls.size, 1)
        let stops = 0
        owner.trackStream({ getTracks: () => [{ stop: () => { stops++ } }] })
        let unsubscriptions = 0
        const waiting = owner.wait(() => false, { subscribe: () => () => { unsubscriptions++ } })
        const rejected = assert.rejects(waiting, { reason: 'owner_disposed' })
        owner.dispose()
        owner.dispose()
        await rejected
        assert.equal(urls.size, 0)
        assert.equal(stops, 1)
        assert.equal(unsubscriptions, 1)
        assert.throws(() => owner.objectUrl('late', new Blob()), { reason: 'owner_disposed' })
    } finally {
        URL.createObjectURL = oldCreate
        URL.revokeObjectURL = oldRevoke
    }
})

test('synchronous subscription readiness disposes its subscription without a reveal delay', async () => {
    const owner = createMediaOwner()
    let ready = false
    let stopped = 0
    assert.equal(await owner.wait(() => ready && 'ready', {
        subscribe(probe) { ready = true; probe(); return () => { stopped++ } },
    }), 'ready')
    assert.equal(stopped, 1)
    owner.dispose()
    assert.equal(stopped, 1)
})

test('a cancelled transport ignores late native completion and preserves the next operation', async () => {
    const owner = createMediaOwner()
    const operations = []
    let cancellations = 0
    const upload = createLivewireUpload({ owner, path: 'data.voice', wire: {
        upload(...args) { operations.push(args) }, cancelUpload() { cancellations++ },
    } })
    const first = upload.start(new Blob(['first']))
    const rejection = assert.rejects(first, { reason: 'upload_cancelled' })
    upload.cancel()
    await rejection
    const second = upload.start(new Blob(['second']))
    operations[0][2]('stale-file')
    assert.equal(upload.uploading, true)
    operations[1][2]('current-file')
    assert.equal(await second, 'current-file')
    assert.equal(cancellations, 1)
    owner.dispose()
})

test('disposal cancels an active upload and progress presentation cannot break persistence', async () => {
    const owner = createMediaOwner()
    let callbacks
    let cancellations = 0
    const upload = createLivewireUpload({ owner, path: 'file', onProgress() { throw new Error('UI failed') }, wire: {
        upload(...args) { callbacks = args }, cancelUpload() { cancellations++ },
    } })
    const promise = upload.start(new Blob(['data']))
    assert.doesNotThrow(() => callbacks[4]({ detail: { progress: 120 } }))
    const rejection = assert.rejects(promise, { reason: 'upload_cancelled' })
    owner.dispose()
    await rejection
    assert.equal(cancellations, 1)
    assert.throws(() => upload.start(new Blob(['late'])), { reason: 'owner_disposed' })
})

test('one form barrier uploads all pending fields then resumes exactly once with the original button', async () => {
    const form = new Form()
    const button = { form, isConnected: true }
    const owners = [createMediaOwner(), createMediaOwner()]
    const pending = [true, true]
    const resolvers = []
    owners.forEach((owner, index) => registerMediaSubmit(form, {
        owner, pending: () => pending[index], persist: () => new Promise((resolve) => {
            resolvers.push(() => { pending[index] = false; resolve() })
        }),
    }))
    form.requestSubmit(button)
    await flush()
    form.requestSubmit(button)
    assert.equal(resolvers.length, 2)
    assert.equal(form.submitted, 0)
    assert.equal(form.getAttribute('aria-busy'), 'true')
    resolvers.forEach((resolve) => resolve())
    await flush()
    assert.equal(form.submitted, 1)
    assert.equal(form.submitter, button)
    assert.equal(form.getAttribute('aria-busy'), null)
    owners.forEach((owner) => owner.dispose())
})

test('failed media prevents submit while a corrected retry can succeed', async () => {
    const form = new Form()
    const owner = createMediaOwner()
    let pending = true
    let attempts = 0
    const errors = []
    registerMediaSubmit(form, { owner, pending: () => pending, persist() {
        if (++attempts === 1) throw new Error('network')
        pending = false
    }, onError: (error) => errors.push(error) })
    form.requestSubmit()
    await flush()
    assert.equal(form.submitted, 0)
    assert.equal(errors.length, 1)
    form.requestSubmit()
    await flush()
    assert.equal(form.submitted, 1)
    owner.dispose()
})

test('owner disposal during deferred persistence never resurrects a form submission', async () => {
    const form = new Form()
    const owner = createMediaOwner()
    let finish
    registerMediaSubmit(form, { owner, pending: () => true, persist: () => new Promise((resolve) => { finish = resolve }) })
    form.requestSubmit()
    await flush()
    owner.dispose()
    finish()
    await flush()
    assert.equal(form.submitted, 0)
    assert.equal(form.getAttribute('aria-busy'), null)
    form.requestSubmit()
    assert.equal(form.submitted, 1)
})

test('pending inspection and native resume failures are reported, not unhandled rejections', async () => {
    const form = new Form()
    const owner = createMediaOwner()
    const errors = []
    let pending = true
    let broken = true
    registerMediaSubmit(form, { owner, pending() { if (broken) throw new Error('state'); return pending },
        persist() { pending = false; form.requestSubmit = () => { throw new Error('detached form') } },
        onError: (error) => errors.push(error),
    })
    form.requestSubmit()
    assert.equal(form.submitted, 0)
    assert.equal(errors[0].reason, 'submit_failed')
    broken = false
    form.requestSubmit()
    await flush()
    assert.equal(errors[1].reason, 'submit_failed')
    assert.equal(form.getAttribute('aria-busy'), null)
    owner.dispose()
})
