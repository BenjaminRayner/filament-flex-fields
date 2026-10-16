import assert from 'node:assert/strict'
import { resolveObjectURL } from 'node:buffer'
import { test } from 'node:test'
import flexFileUpload from '../../resources/js/components/flex-file-upload.js'
import { createMediaOwner } from '../../resources/js/media/lifecycle/owner.js'
import { createFileList } from '../../resources/js/file-upload/presentation/file-list.js'
import { fileIconUrl } from '../../resources/js/file-upload/presentation/file-icon.js'

test('file icons use the shared MIME mapping and safe declared fallback URLs', () => {
    const catalog = { urls: { attachment: '/icons/attachment.svg', document: '/icons/document.svg', image: '/icons/image.svg', sheet: '/icons/sheet.svg' },
        extensions: { xlsx: 'sheet' }, mimeTypes: { 'application/pdf': 'document' }, mimePrefixes: { 'image/': 'image' } }
    assert.equal(fileIconUrl({ extension: 'XLSX' }, catalog), '/icons/sheet.svg')
    assert.equal(fileIconUrl({ extension: 'XLSX', type: 'application/pdf' }, catalog), '/icons/document.svg')
    assert.equal(fileIconUrl({ extension: 'bin', type: 'IMAGE/PNG; charset=binary' }, catalog), '/icons/image.svg')
    assert.equal(fileIconUrl({ extension: '../../outside' }, catalog), '/icons/attachment.svg')
    assert.equal(fileIconUrl({}, {}), null)
})

test('native files retain their full extension for the artwork catalog', () => {
    const owner = createMediaOwner()
    const list = createFileList({ owner })
    const pond = { getFiles: () => [{ id: 'design', filename: 'mockup.sketch', status: 2, origin: 1 }] }
    const file = list.update({ pond, ready: true })[0]
    assert.equal(file.extension, 'SKETCH')
    assert.equal(fileIconUrl(file, { urls: { sketch: '/icons/sketch.svg' }, extensions: { sketch: 'sketch' } }), '/icons/sketch.svg')
    owner.dispose()
})

test('click outline expires after one second, restarts on another click and releases its timer', t => {
    t.mock.timers.enable({ apis: ['setTimeout'] })
    const component = flexFileUpload()
    component.pulseOutline()
    assert.equal(component.outlineActive, true)
    t.mock.timers.tick(900)
    component.pulseOutline()
    t.mock.timers.tick(999)
    assert.equal(component.outlineActive, true)
    t.mock.timers.tick(1)
    assert.equal(component.outlineActive, false)
    component.pulseOutline()
    component.destroy()
    t.mock.timers.tick(1000)
    assert.equal(component.outlineActive, true) // Disposed presentation must not be mutated.
    const disabled = flexFileUpload({ isDisabled: true })
    disabled.pulseOutline()
    assert.equal(disabled.outlineActive, false)
    disabled.destroy()
})

test('native avatar drop resets nested drag depth before the next drag', () => {
    const component = flexFileUpload()
    const event = { preventDefault() {}, dataTransfer: { types: ['Files'] } }
    component.dragEnter(event)
    component.dragEnter(event)
    assert.equal(component.isDragging, true)
    component.finishDrag()
    assert.equal(component.isDragging, false)
    component.dragEnter(event)
    component.dragLeave(event)
    assert.equal(component.isDragging, false)
    component.destroy()
})

test('upload initialization is idempotent and preserves SSR files until native readiness', async () => {
    const initial = [{ id: 'stored:a', name: 'a.pdf', bytes: 2048, complete: true }]
    const component = flexFileUpload({ initialFiles: initial, showUploadSummary: true, summaryTemplate: ':count files · :size KB' })
    component.$nextTick = callback => Promise.resolve().then(callback)
    const first = component.init()
    assert.equal(component.init(), first)
    assert.equal(component.files, initial)
    assert.equal(component.summaryLabel, '1 files · 2 KB')
    await first
    await Promise.resolve()
    assert.equal(component.presentationMounted, true)
    component.destroy()
})

test('native events produce bounded progress and real ETA without changing native data', () => {
    const owner = createMediaOwner()
    let elapsed = 0
    const list = createFileList({ owner, now: () => elapsed })
    const file = { id: 'a', filename: 'a.pdf', status: 3, fileSize: 2048, origin: 1 }
    const pond = { getFiles: () => [file] }
    list.update({ pond, ready: true, event: 'processfilestart', args: [file] })
    elapsed = 2000
    const row = list.update({ pond, ready: true, event: 'processfileprogress', args: [file, .5] })[0]
    assert.equal(row.progress, 50)
    assert.equal(row.remaining, 2)
    assert.equal(file.status, 3)
    assert.equal(list.update({ pond, ready: true, event: 'processfileprogress', args: [file, 2] })[0].progress, 100)
    assert.equal(list.update({ pond, ready: false }), null)
    owner.dispose()
})

test('grid progress stays hidden through loading and queued transfer until real bytes advance, including retries', () => {
    const owner = createMediaOwner(), list = createFileList({ owner })
    const file = { id: 'waiting', origin: 1, status: 1 }
    const pond = { getFiles: () => [file] }
    try {
        assert.equal(list.update({ pond, ready: true })[0].progressVisible, false)
        file.status = 9
        assert.equal(list.update({ pond, ready: true })[0].progressVisible, false)
        file.status = 3
        let row = list.update({ pond, ready: true, event: 'processfilestart', args: [file] })[0]
        assert.equal(row.progressVisible, false)
        row = list.update({ pond, ready: true, event: 'processfileprogress', args: [file, 0] })[0]
        assert.equal(row.progressVisible, false)
        row = list.update({ pond, ready: true, event: 'processfileprogress', args: [file, .0015] })[0]
        assert.equal(row.progressVisible, true)
        assert.equal(row.progressValue, .15)
        assert.equal(row.progress, 0) // Precise path progress must not be lost to label rounding.
        file.status = 8
        assert.equal(list.update({ pond, ready: true })[0].progressVisible, false)
        file.status = 3
        row = list.update({ pond, ready: true, event: 'processfilestart', args: [file] })[0]
        assert.equal(row.progressVisible, false)
        assert.equal(row.progressValue, 0)
        file.status = 5
        row = list.update({ pond, ready: true })[0]
        assert.equal(row.progressVisible, false)
        assert.equal(row.progressValue, 100)
    } finally { owner.dispose() }
})

test('server rows retain their key when FilePond assigns a different native item id', () => {
    const owner = createMediaOwner()
    const list = createFileList({ owner })
    const pond = { getFiles: () => [{ id: 'pond-id', source: '/signed/preview', filename: 'a.png', origin: 3, status: 2 }] }
    const rows = list.update({ pond, component: { uploadedFileIndex: { '/signed/preview': 'server-key' } }, ready: true })
    assert.equal(rows[0].id, 'stored:server-key')
    assert.equal(rows[0].pondId, 'pond-id')
    assert.equal(rows[0].complete, true)
    owner.dispose()
})

test('preview replacement and file removal release their object URLs', () => {
    const owner = createMediaOwner()
    const list = createFileList({ owner })
    const file = { id: 'a', filename: 'a.png', fileType: 'image/png', origin: 1, status: 5, file: new Blob(['a']) }
    const files = [file]
    const pond = { getFiles: () => files }
    const first = list.update({ pond, ready: true })[0].preview
    file.file = new Blob(['b'])
    const second = list.update({ pond, ready: true })[0].preview
    assert.notEqual(first, second)
    assert.equal(resolveObjectURL(first), undefined)
    files.length = 0
    list.update({ pond, ready: true })
    assert.equal(resolveObjectURL(second), undefined)
    owner.dispose()
})

test('disabled upload never invokes native selection, replacement or actions', async () => {
    const component = flexFileUpload({ isDisabled: true })
    await component.browse()
    await component.fileAction('a', 'remove')
    await component.drop({ preventDefault() {}, dataTransfer: { files: [] } })
    assert.equal(component.actionError, null)
    component.destroy()
})

test('border progress follows native failure, retry and completion rather than a simulated timer', () => {
    const owner = createMediaOwner()
    const list = createFileList({ owner, now: () => 2000 })
    const file = { id: 'upload', filename: 'payroll.pdf', origin: 1, status: 3 }
    const pond = { getFiles: () => [file] }
    let row = list.update({ pond, ready: true, event: 'processfileprogress', args: [file, .37] })[0]
    assert.equal(row.progress, 37)
    assert.equal(row.uploading, true)
    file.status = 8
    row = list.update({ pond, ready: true })[0]
    assert.equal(row.failed, true)
    assert.equal(row.uploading, false)
    file.status = 3
    row = list.update({ pond, ready: true, event: 'processfilestart', args: [file] })[0]
    assert.equal(row.progress, 0)
    assert.equal(row.failed, false)
    file.status = 5
    row = list.update({ pond, ready: true })[0]
    assert.equal(row.progress, 100)
    assert.equal(row.complete, true)
    assert.equal(row.uploading, false)
    owner.dispose()
})

test('success tint represents a fresh successful upload, never a restored or failed image', () => {
    const owner = createMediaOwner(), list = createFileList({ owner })
    const file = { id: 'avatar', origin: 1, status: 2 }
    const pond = { getFiles: () => [file] }
    assert.equal(list.update({ pond, ready: true })[0].uploaded, false)
    file.status = 8
    assert.equal(list.update({ pond, ready: true })[0].uploaded, false)
    file.status = 5
    assert.equal(list.update({ pond, ready: true })[0].uploaded, true)
    file.origin = 3
    assert.equal(list.update({ pond, ready: true })[0].uploaded, false)
    owner.dispose()
})

test('restored file failures override completed presentation and never display transfer progress', () => {
    const owner = createMediaOwner(), list = createFileList({ owner })
    const file = { id: 'restored', origin: 3, filename: 'photo.png' }
    const pond = { getFiles: () => [file] }
    try {
        for (const status of [6, 8, 10]) {
            file.status = status
            const row = list.update({ pond, ready: true })[0]
            assert.equal(row.failed, true)
            assert.equal(row.complete, false)
            assert.equal(row.uploaded, false)
            assert.equal(row.progressVisible, false)
        }
    } finally { owner.dispose() }
})

test('a manual field processes pending native files before resuming its form once', async () => {
    const { EventEmitter } = await import('node:events')
    class Form extends EventTarget {
        attributes = new Map()
        submitted = 0
        getAttribute(key) { return this.attributes.get(key) ?? null }
        setAttribute(key, value) { this.attributes.set(key, value) }
        removeAttribute(key) { this.attributes.delete(key) }
        requestSubmit() {
            const event = new Event('submit', { cancelable: true })
            this.dispatchEvent(event)
            if (!event.defaultPrevented) this.submitted++
        }
    }
    const form = new Form(), events = new EventEmitter()
    const file = { id: 'chosen', filename: 'avatar.png', status: 2, origin: 1 }
    let processes = 0, finish
    const pond = { instantUpload: true, getFiles: () => [file],
        on: events.on.bind(events), off: events.off.bind(events),
        setOptions(update) { Object.assign(this, update) },
        processFile() {
            processes++
            file.status = 3
            return new Promise(resolve => { finish = () => { file.status = 5; events.emit('processfile', null, file); resolve(file) } })
        },
    }
    const component = flexFileUpload({ instantUpload: false })
    component.$el = { closest: () => form }
    component.$nextTick = callback => Promise.resolve().then(callback)
    await component.init()
    component.registerFilePond({ component: {}, pond, ready: true })
    form.requestSubmit()
    await new Promise(resolve => setImmediate(resolve))
    assert.equal(processes, 1)
    assert.equal(form.submitted, 0)
    finish()
    await new Promise(resolve => setImmediate(resolve))
    assert.equal(form.submitted, 1)
    component.destroy()
    assert.equal(events.eventNames().length, 0)
    assert.equal(pond.instantUpload, true)
})

test('reorder persists native completed and restored keys in both display order conventions', async () => {
    const { EventEmitter } = await import('node:events')
    for (const shouldAppendFiles of [true, false]) {
        const events = new EventEmitter(), requests = []
        const items = [{ id: 'new', source: new Blob(['new']), serverId: 'temporary', status: 5, origin: 1 },
            { id: 'existing', source: '/signed/existing', status: 2, origin: 3 }]
        const pond = { getFiles: () => items, on: events.on.bind(events), off: events.off.bind(events), setOptions() {},
            moveFile(id, index) { const from = items.findIndex(item => item.id === id); items.splice(index, 0, items.splice(from, 1)[0]) } }
        const native = { uploadedFileIndex: { '/signed/existing': 'stored' }, $wire: { async callSchemaComponentMethod(...args) { requests.push(args) } } }
        const component = flexFileUpload({ isReorderable: true, shouldAppendFiles, schemaComponentKey: 'attachments' })
        component.$nextTick = callback => callback()
        await component.init()
        component.registerFilePond({ component: native, pond, ready: true })
        try {
            await component.moveFile('new', 1)
            assert.deepEqual(component.files.map(file => file.pondId), ['existing', 'new'])
            assert.deepEqual(requests, [['attachments', 'reorderUploadedFiles', { fileKeys: shouldAppendFiles ? ['stored', 'temporary'] : ['temporary', 'stored'] }]])
            await component.moveFile('new', 1)
            assert.equal(requests.length, 1)
        } finally { component.destroy() }
    }
})

test('resolved native failures clear temporary server errors only after every failed item is resolved', async () => {
    const { EventEmitter } = await import('node:events')
    const events = new EventEmitter(), requests = []
    const items = [{ id: 'first', status: 6, origin: 1 }, { id: 'second', status: 6, origin: 1 }]
    const pond = { getFiles: () => items, on: events.on.bind(events), off: events.off.bind(events), setOptions() {} }
    const native = { error: 'Native failure', $wire: { async callSchemaComponentMethod(...args) { requests.push(args) } } }
    const component = flexFileUpload({ schemaComponentKey: 'attachments' })
    component.$nextTick = callback => callback()
    await component.init()
    component.registerFilePond({ component: native, pond, ready: true })
    try {
        items.shift()
        events.emit('removefile', null, { id: 'first' })
        assert.equal(requests.length, 0)
        items[0].status = 5
        events.emit('processfile', null, items[0])
        await new Promise(resolve => setImmediate(resolve))
        assert.deepEqual(requests, [['attachments', 'clearTemporaryUploadErrors']])
        assert.equal(native.error, null)
        events.emit('processfile', null, items[0])
        assert.equal(requests.length, 1)
    } finally { component.destroy() }
})

test('viewer resolves late transport IDs for every origin before mounting its action', async () => {
    const { EventEmitter } = await import('node:events')
    const events = new EventEmitter(), mounted = []
    const items = [{ id: 'first', status: 5, origin: 1 }, { id: 'second', status: 5, origin: 1 },
        { id: 'existing', source: '/preview/existing', status: 2, origin: 3 }]
    const pond = { getFiles: () => items, on: events.on.bind(events), off: events.off.bind(events), setOptions() {} }
    const component = flexFileUpload({ hasFileViewer: true, schemaComponentKey: 'photos' })
    component.$nextTick = callback => Promise.resolve().then(() => callback?.())
    component.$wire = { async mountAction(...args) {
        mounted.push(args)
        assert.deepEqual(component.files.map(file => file.viewerKey), ['late-first', 'late-second', 'existing-key'])
    } }
    await component.init()
    component.registerFilePond({ component: { uploadedFileIndex: { '/preview/existing': 'existing-key' } }, pond, ready: true })
    try {
        assert.equal(component.files[0].viewerKey, null)
        items[0].serverId = 'late-first'; items[1].serverId = 'late-second'
        await component.fileAction('first', 'viewer')
        assert.deepEqual(mounted, [['viewFile', { fileKey: 'late-first' }, { schemaComponent: 'photos' }]])
        assert.equal(items[0].serverId, 'late-first')
    } finally { component.destroy() }
})

test('rapid viewer clicks mount only one action and release the guard after rejection', async () => {
    const { EventEmitter } = await import('node:events')
    const events = new EventEmitter(), file = { id: 'photo', serverId: 'uploaded', status: 5, origin: 1 }
    const pond = { getFiles: () => [file], on: events.on.bind(events), off: events.off.bind(events), setOptions() {} }
    let mounts = 0, reject
    const component = flexFileUpload({ hasFileViewer: true, schemaComponentKey: 'photos' })
    component.$nextTick = callback => Promise.resolve().then(() => callback?.())
    component.$wire = { mountAction() { mounts++; return new Promise((resolve, fail) => { reject = fail }) } }
    await component.init(); component.registerFilePond({ component: {}, pond, ready: true })
    try {
        const first = component.fileAction('photo', 'viewer')
        await new Promise(resolve => setImmediate(resolve))
        await component.fileAction('photo', 'viewer')
        assert.equal(mounts, 1)
        reject(Error('request rejected')); await first
        assert.ok(component.actionError)
        component.$wire.mountAction = async () => { mounts++ }
        await component.fileAction('photo', 'viewer')
        assert.equal(mounts, 2); assert.equal(component.actionError, null)
    } finally { component.destroy() }
})

test('tile-click preview uses the existing viewer action and rejects incomplete or non-image tiles', async () => {
    const component = flexFileUpload({ fileClickPreview: true }), calls = []
    assert.equal(component.fileClickPreview, true)
    component.fileAction = async (file, action) => { calls.push([file.pondId, action]) }
    await component.activateUploadedFile({ pondId: 'photo', complete: true, preview: '/photo.jpg' })
    await component.activateUploadedFile({ pondId: 'pdf', complete: true })
    await component.activateUploadedFile({ pondId: 'waiting', complete: false, preview: '/photo.jpg' })
    await component.activateUploadedFile({ pondId: 'failed', complete: true, failed: true, preview: '/photo.jpg' })
    assert.deepEqual(calls, [['photo', 'viewer']])
    component.destroy()
})
