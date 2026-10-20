import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import { createServer } from 'node:http'
import { build } from 'esbuild'
import { launchCoreBrowser, browserEngine } from './browser-profile.mjs'

const root = path.resolve(import.meta.dirname, '../..')
const seal = JSON.parse(fs.readFileSync(path.join(root, 'resources/dist/asset-versions.json')))
const native = await build({ stdin: { resolveDir: root, contents: `import { Alpine } from './vendor/livewire/livewire/dist/livewire.esm.js'; window.Alpine = Alpine;` }, bundle: true, format: 'esm', minify: true, write: false })
const server = createServer((request, response) => {
    const name = new URL(request.url, 'http://localhost').pathname
    if (name === '/') { response.writeHead(200, { 'Content-Type': 'text/html' }); return response.end('<!doctype html><body><main id="owners"></main><script type="module" src="/native.js"></script>') }
    if (name === '/native.js') { response.writeHead(200, { 'Content-Type': 'text/javascript' }); return response.end(native.outputFiles[0].contents) }
    const asset = name.slice(1)
    if (asset.startsWith('components/') && Object.hasOwn(seal.assets, asset) && asset.endsWith('.js')) {
        response.writeHead(200, { 'Content-Type': 'text/javascript' }); return response.end(fs.readFileSync(path.join(root, 'resources/dist', asset)))
    }
    response.writeHead(404); response.end()
})
let browser
try {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve))
    browser = await launchCoreBrowser({ headless: true })
    const page = await browser.newPage(), errors = []
    page.on('pageerror', error => errors.push(error.message))
    await page.goto(`http://127.0.0.1:${server.address().port}/`, { waitUntil: 'domcontentloaded' })
    await page.waitForFunction(() => window.Alpine)
    const result = await page.evaluate(async () => {
        const { createResourceScope } = await import('/components/flex-runtime-sdk.js')
        const context = new AudioContext(), source = context.createMediaStreamDestination(), master = source.stream.getAudioTracks()[0]
        if (!master || master.readyState !== 'live') throw Error('Synthetic audio track is not available')
        const timers = new Set(), originalSet = window.setInterval, originalClear = window.clearInterval
        window.setInterval = function (...args) { const id = originalSet.apply(this, args); timers.add(id); return id }
        window.clearInterval = function (id) { timers.delete(id); return originalClear.call(this, id) }
        let mounted = 0, destroyed = 0, stopped = 0, events = 0
        const target = new EventTarget(), instances = []
        Alpine.data('ownedSyntheticCapture', () => {
            const scope = createResourceScope(), track = master.clone(), stream = new MediaStream([track])
            return { scope, track,
                init() { mounted++; scope.listen(target, 'owned-check', () => events++); scope.interval(() => {}, 100000); scope.trackStream(stream) },
                destroy() { destroyed++; scope.dispose() },
            }
        })
        Alpine.start()
        try {
            for (let cycle = 0; cycle < 100; cycle++) {
                const element = document.createElement('div'); element.setAttribute('x-data', 'ownedSyntheticCapture()'); document.querySelector('#owners').append(element)
                await new Promise(resolve => requestAnimationFrame(resolve))
                const data = element._x_dataStack?.[0]
                if (!data || data.scope.snapshot().resources !== 3 || data.track.readyState !== 'live' || timers.size !== 1) throw Error('Mounted owner did not adopt all real effects')
                target.dispatchEvent(new Event('owned-check'))
                if (events !== cycle + 1) throw Error('Owned listener did not fire exactly once')
                element.remove()
                await new Promise(resolve => requestAnimationFrame(resolve))
                if (!data.scope.disposed || data.scope.snapshot().resources !== 0 || data.track.readyState !== 'ended' || timers.size !== 0) throw Error('Alpine removal left a live effect')
                target.dispatchEvent(new Event('owned-check'))
                if (events !== cycle + 1 || master.readyState !== 'live') throw Error('Disposal retained a listener or stopped its shared source')
                stopped++; instances.push(data)
            }
            return { mounted, destroyed, stopped, remainingTimers: timers.size,
                remainingOwnedResources: instances.reduce((sum, instance) => sum + instance.scope.snapshot().resources, 0),
                liveOwnedTracks: instances.filter(instance => instance.track.readyState !== 'ended').length,
                sharedSourcePreserved: master.readyState === 'live' }
        } finally {
            window.setInterval = originalSet; window.clearInterval = originalClear
            master.stop(); await context.close()
        }
    })
    assert.deepEqual(result, { mounted: 100, destroyed: 100, stopped: 100, remainingTimers: 0, remainingOwnedResources: 0, liveOwnedTracks: 0, sharedSourcePreserved: true })
    assert.deepEqual(errors, [])
    console.log(JSON.stringify({ passed: true, browser: browserEngine, ...result, errors,
        scope: 'Native installed Alpine removal and the actual compiled public SDK own real EventTarget listeners, browser intervals and synthetic WebAudio MediaStreamTrack clones. No hardware microphone, camera, recording service or claim that every capture component was exercised.' }))
} finally {
    await browser?.close()
    server.closeAllConnections(); if (server.listening) await new Promise(resolve => server.close(resolve))
}
