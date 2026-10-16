import assert from 'node:assert/strict'
import { createServer } from 'node:http'
import { build } from 'esbuild'
import { launchCoreBrowser } from './browser-profile.mjs'

// The engine is private source, not a new public standalone SDK entry. Native
// publication is exercised separately by the existing actual-release probes.
const compiled = await build({ stdin: { resolveDir: process.cwd(), contents: `
import { createAssetRuntime } from './resources/js/runtime/asset-runtime-engine.js';
window.createDomRuntime = options => createAssetRuntime({ document, window, ...options });` },
    bundle: true, write: false, minify: true, format: 'iife', metafile: true, logLevel: 'silent' })
assert(!Object.keys(compiled.metafile.inputs).some(name => name.includes('/filament/') || name.includes('fff-load-directive')))
const batch = styles => `<span hidden data-fff-asset-batch data-fff-asset-consumer="author" data-fff-stylesheets='${JSON.stringify(styles)}' data-fff-chunks="[]"></span>`
const requests = [], waiting = [], errors = [], proofs = []
const server = createServer((request, response) => {
    const route = new URL(request.url, 'http://localhost').pathname
    const send = (type, body) => { response.writeHead(200, { 'Content-Type': type, 'Cache-Control': 'no-store' }); response.end(body) }
    if (route === '/') return send('text/html', `<!doctype html><html><body>
<section id="page">${batch(['/page.css'])}Page</section>
<section id="parent" hidden><section>${batch(['/shared.css', '/parent.css'])}<div id="parent-factory" x-fff-load></div></section>
<section id="child" hidden><section>${batch(['/shared.css', '/child.css'])}</section></section></section>
<section id="factory-race" hidden><section>${batch([])}<div x-fff-load></div></section></section>
<section id="race" hidden><section>${batch(['/slow.css'])}</section></section>
<script src="/engine.js"></script></body></html>`)
    if (route === '/engine.js') return send('text/javascript', compiled.outputFiles[0].contents)
    if (route.endsWith('.css')) {
        requests.push(route)
        if (route === '/slow.css') { waiting.push(() => send('text/css', '#race { color: green }')); return }
        return send('text/css', 'section { box-sizing: border-box }')
    }
    if (route === '/favicon.ico') { response.writeHead(204); response.end(); return }
    response.writeHead(404); response.end()
})
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve))
const browser = await launchCoreBrowser({ headless: true }), page = await browser.newPage()
page.on('pageerror', error => errors.push(String(error)))
page.on('console', message => { if (message.type() === 'error') errors.push(message.text()) })
try {
    await page.goto(`http://127.0.0.1:${server.address().port}/`)
    await page.evaluate(() => {
        window.events = []; window.hooks = []; window.factoryTimers = new Set()
        const setTimer = window.setTimeout.bind(window), clearTimer = window.clearTimeout.bind(window)
        window.setTimeout = (callback, delay, ...args) => {
            const id = setTimer(callback, delay, ...args); if (delay === 15000) factoryTimers.add(id); return id
        }
        window.clearTimeout = id => { factoryTimers.delete(id); return clearTimer(id) }
        for (const target of [document, window]) {
            const add = target.addEventListener.bind(target)
            target.addEventListener = (name, listener, options) => { if (/livewire|modal|segment/.test(name)) events.push(name); return add(name, listener, options) }
        }
        window.Livewire = { hook(name) { hooks.push(name); throw Error('unrequested native hooks') } }
        window.runtime = createDomRuntime(); runtime.boot()
        window.parentHandle = runtime.registerAssetSurface(document.getElementById('parent'))
        window.childHandle = runtime.registerAssetSurface(document.getElementById('child'), { parent: parentHandle })
        window.raceHandle = runtime.registerAssetSurface(document.getElementById('race'))
        window.factoryRaceHandle = runtime.registerAssetSurface(document.getElementById('factory-race'))
    })
    await page.waitForFunction(() => runtime.isStylesheetLoaded('/page.css'))
    assert.deepEqual(await page.evaluate(() => ({ events, hooks, nativeModalApi: typeof runtime.prepareModal })), { events: [], hooks: [], nativeModalApi: 'undefined' })
    assert(!requests.includes('/shared.css')); assert(!requests.includes('/child.css'))
    proofs.push('actual standalone-engine browser boot acquires page CSS only; dormant surfaces request nothing and native hook/event APIs remain unused')

    await page.evaluate(() => { window.parentResult = null; parentHandle.activate().then(value => parentResult = value) })
    await page.waitForFunction(() => runtime.isStylesheetLoaded('/parent.css'))
    assert.equal(await page.evaluate(() => parentResult), null)
    assert.deepEqual(await page.evaluate(() => events), [])
    await page.evaluate(() => {
        const field = document.getElementById('parent-factory'); field._fffFactoryReady = true
        field.dataset.fffAlpineReady = 'true'; field.dispatchEvent(new CustomEvent('fff-load-ready', { bubbles: true }))
    })
    await page.waitForFunction(() => parentResult === true && parentHandle.ready)
    await page.evaluate(async () => { parentHandle.suspend(); window.childResult = await childHandle.activate() })
    assert.equal(await page.evaluate(() => childResult), true)
    assert.equal(requests.filter(route => route === '/shared.css').length, 1)
    await page.evaluate(() => childHandle.close())
    assert.deepEqual(await page.evaluate(() => ({ parent: runtime.isStylesheetLoaded('/parent.css'), shared: runtime.isStylesheetLoaded('/shared.css'), active: runtime.isElementActive(document.getElementById('parent')) })), { parent: true, shared: true, active: true })
    proofs.push('native DOM factory event/attribute completes readiness without framework events; nested suspended parent retains shared CSS after child close, with one HTTP shared request')

    await page.evaluate(() => { window.raceResult = null; raceHandle.activate().then(value => raceResult = value) })
    await page.waitForFunction(() => [...document.querySelectorAll('link')].some(link => link.href.endsWith('/slow.css')))
    for (let i = 0; i < 120 && !waiting.length; i++) await new Promise(resolve => setTimeout(resolve, 10))
    assert.equal(waiting.length, 1)
    await page.evaluate(() => raceHandle.close())
    await page.waitForFunction(() => raceResult === false)
    waiting.splice(0).forEach(finish => finish())
    await page.waitForFunction(() => runtime.getInflightUrls().length === 0)
    assert.equal(await page.evaluate(() => raceHandle.ready), false)
    proofs.push('closing a custom surface cancels readiness immediately during delayed real HTTP; late resource completion never reactivates it')

    await page.evaluate(() => { window.factoryRaceResult = null; factoryRaceHandle.activate().then(value => factoryRaceResult = value) })
    await page.waitForFunction(() => factoryTimers.size === 1)
    assert.equal(await page.evaluate(() => factoryRaceResult), null)

    await page.evaluate(async html => {
        const body = document.createElement('body'); body.innerHTML = `<section id="next">${html}</section>`
        document.body.replaceWith(body); window.refreshed = await runtime.refreshDocument()
        const field = document.createElement('section'); field.innerHTML = `<span hidden data-fff-asset-batch data-fff-asset-consumer="author" data-fff-stylesheets='["/inserted.css"]' data-fff-chunks="[]"></span>`
        body.appendChild(field)
    }, batch(['/replacement.css']))
    await page.waitForFunction(() => runtime.isStylesheetLoaded('/replacement.css') && runtime.isStylesheetLoaded('/inserted.css'))
    assert.equal(await page.evaluate(() => refreshed), true)
    await page.waitForFunction(() => factoryRaceResult === false && factoryTimers.size === 0)
    proofs.push('replacement-body refresh retires an unready detached factory and cancels its real 15-second deadline immediately, without a framework event')
    assert.deepEqual(await page.evaluate(() => events), [])
    await page.evaluate(() => runtime.dispose())
    assert.equal(await page.evaluate(() => runtime.isDisposed()), true)
    proofs.push('real body replacement followed by explicit refresh reconnects MutationObserver; a later inserted field loads without Livewire navigation or native events')
    assert.deepEqual(errors, [])
    console.log(JSON.stringify({ browser: browser.version(), sourceEngineCompiled: true, nativeAdapterInputs: 0, groups: proofs.length, proofs, errors }, null, 2))
} finally {
    waiting.splice(0).forEach(finish => finish())
    await browser.close(); await new Promise(resolve => server.close(resolve))
}
