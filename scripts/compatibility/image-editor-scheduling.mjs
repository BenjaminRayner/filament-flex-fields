import assert from 'node:assert/strict'
import { build } from 'esbuild'
import { fileURLToPath } from 'node:url'

const bundle = await build({ entryPoints: [fileURLToPath(new URL('../../resources/js/file-upload/editor/adjustment-runtime.js', import.meta.url))], bundle: true, write: false, format: 'esm', platform: 'node' })
const { createAdjustmentRuntime } = await import(`data:text/javascript;base64,${Buffer.from(bundle.outputFiles[0].contents).toString('base64')}`)
const requests = [], frames = [], urls = [], releases = []
let worker, cleanup
const previous = { Worker: globalThis.Worker, Image: globalThis.Image, ImageData: globalThis.ImageData, document: globalThis.document }
try {
    globalThis.Worker = class {
        constructor() { worker = this }
        postMessage(data) { if (!data.base && !data.fullSource) requests.push(data) }
        terminate() { this.stopped = true }
    }
    globalThis.Image = class { async decode() {} }
    globalThis.ImageData = class { constructor(data, width, height) { Object.assign(this, { data, width, height }) } }
    globalThis.document = { createElement: () => ({ width: 0, height: 0,
        getContext: () => ({ drawImage() {}, getImageData: () => ({data: new Uint8ClampedArray(16)}), putImageData() {} }),
        toBlob: callback => callback(new Blob(['frame'], {type:'image/png'})),
    }) }
    const owner = { disposed: false, defer: callback => { cleanup = callback; return callback },
        objectUrl: (name, blob) => { urls.push(name); return `blob:${urls.length}` }, releaseUrl: name => releases.push(name),
    }
    const runtime = createAdjustmentRuntime({owner, image: {naturalWidth:2,naturalHeight:2}, apply: async url => { frames.push(url) }})
    await runtime.initialize()
    const rendering = runtime.schedule({brightness:10})
    runtime.schedule({brightness:20})
    runtime.schedule({brightness:30})
    assert.equal(requests.length, 1, 'Only one pixel request may be active')
    worker.onmessage({data:{pixels:new Uint8ClampedArray(16).buffer}})
    await new Promise(resolve => setImmediate(resolve))
    assert.equal(frames.length, 1, 'Continuous slider input must publish completed intermediate frames before the latest frame finishes')
    assert.equal(requests.length, 2)
    assert.equal(requests[1].values.brightness, 30, 'Pending input coalesces to the latest settings')
    worker.onmessage({data:{pixels:new Uint8ClampedArray(16).buffer}})
    await rendering
    assert.equal(frames.length, 2)
    const exportReady = runtime.flush()
    await new Promise(resolve => setImmediate(resolve))
    assert.equal(requests[2].full, true, 'Save must request original-resolution rendering')
    worker.onmessage({data:{pixels:new Uint8ClampedArray(16).buffer}})
    await exportReady
    assert.equal(frames.length, 3)
    const pending = runtime.schedule({brightness:40})
    cleanup()
    await assert.rejects(pending)
    assert.equal(worker.stopped, true)
    assert.ok(releases.includes('editor-adjust-image') && releases.includes('editor-adjust-worker'))
    assert.equal(frames.length, 3, 'Disposal must prevent late frame publication')
    console.log('Image editor scheduling: continuous preview, latest-input coalescing, full-resolution flush and pending disposal passed.')
} finally {
    for (const [name, value] of Object.entries(previous)) {
        if (value === undefined) delete globalThis[name]
        else globalThis[name] = value
    }
}
