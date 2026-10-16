import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import { createServer } from 'node:http'
import { spawnSync } from 'node:child_process'
import { launchCoreBrowser } from './browser-profile.mjs'
import { createHash } from 'node:crypto'

// Compiled synchronous owner -> native ESM sharing is not covered by source tests.
const root = path.resolve(import.meta.dirname, '../..')
const rendering = spawnSync('php', ['scripts/compatibility/core-first-paint-rendering.php', '--json'], { cwd: root, encoding: 'utf8' })
assert.equal(rendering.status, 0, rendering.stderr)
const scripts = JSON.parse(rendering.stdout)
const dist = path.join(root, 'resources/dist/components')
const manifest = JSON.parse(fs.readFileSync(path.join(dist, 'alpine-manifest.json')))
const files = new Set([...manifest.__entries__.map(name => `${name}.js`), ...manifest.__shared_chunks__])
const owners = Object.fromEntries(['segment', 'timezone'].map(name => [name,
    manifest.__shared_chunks__.find(file => file.startsWith(`flex-fields-first-paint-${name}-`))]))
for (const [name, file] of Object.entries(owners)) {
    const bytes = fs.readFileSync(path.join(dist, file))
    assert.ok(file.endsWith(`${createHash('sha256').update(bytes).digest('hex')}.js`), `${name} filename is not a content hash`)
    assert.equal(bytes.toString(), scripts[name][0], 'SSR emitted different bytes from the native ESM owner')
}
const shell = (id, direction = 'ltr', script = '') => `<div id="${id}" dir="${direction}" class="shell" data-fff-segment-overflow><div class="track">${Array.from({ length: 8 }, (_, index) => `<button data-segment-value="${index}" data-segment-selected="${index === 4}">${index}</button>`).join('')}<span class="fff-segment-indicator"></span></div>${script ? `<script>${script}</script>` : ''}</div>`
const timezone = (id, script) => `<div id="${id}" class="fff-timezone-field"><span class="fff-timezone-field__ssr-label is-placeholder" data-fff-tz-ssr-provisional>Placeholder</span><span class="fff-timezone-field__ssr-meta"></span><div data-fff-timezone-boot data-fff-timezone-catalog='{"Europe/Warsaw":["Warsaw","UTC+02:00"]}' hidden></div><script>${script}</script></div>`
const measure = id => `<script>window.initial.push({id:'${id}',left:document.getElementById('${id}').scrollLeft,done:document.getElementById('${id}').dataset.ssrScrollPositioned})</script>`
const style = '<style>.shell{width:240px;overflow:auto}.track{display:flex;width:max-content;position:relative}.track button{flex:none;width:100px;height:32px}.fff-segment-indicator{position:absolute}</style>'
const requests = [], errors = [], proofs = []
const server = createServer((request, response) => {
    const route = request.url.split('?')[0]
    if (route === '/' || route === '/cold') {
        response.writeHead(200, { 'Content-Type': 'text/html', 'Cache-Control': 'no-store' })
        const content = route === '/cold' ? '' : `<script>window.initial=[]</script>${shell('ltr', 'ltr', scripts.segment[0])}${measure('ltr')}${shell('rtl', 'rtl', scripts.segment[1])}${measure('rtl')}<div hidden id="dormant">${shell('hidden-shell', 'ltr', scripts.segment[1])}</div>${timezone('tz-first', scripts.timezone[0])}${timezone('tz-second', scripts.timezone[1])}<script>window.originalSegment=globalThis[Symbol.for('fff.first-paint.segment.v1')];window.originalTimezone=globalThis[Symbol.for('fff.first-paint.timezone.v1')]</script>`
        return response.end(`<!doctype html><html><head><meta charset="utf-8">${style}</head><body>${content}</body></html>`)
    }
    const name = route.slice(1)
    if (files.has(name)) {
        requests.push(name)
        response.writeHead(200, { 'Content-Type': 'text/javascript', 'Cache-Control': 'no-store' })
        return response.end(fs.readFileSync(path.join(dist, name)))
    }
    response.writeHead(404); response.end()
})
let browser
try {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve))
    browser = await launchCoreBrowser({ headless: true })
    const context = await browser.newContext({ timezoneId: 'Europe/Warsaw' })
    const page = await context.newPage()
    page.on('pageerror', error => errors.push(error.message))
    await page.goto(`http://127.0.0.1:${server.address().port}/`)
    const initial = await page.evaluate(() => ({ frames: window.initial, hidden: document.getElementById('hidden-shell').dataset.ssrScrollPositioned,
        labels: [...document.querySelectorAll('.fff-timezone-field__ssr-label')].map(node => node.textContent),
        frozen: Object.isFrozen(window.originalSegment) && Object.isFrozen(window.originalTimezone) }))
    assert.equal(requests.length, 0, 'First paint depends on asynchronous HTTP')
    assert.ok(initial.frames.every(frame => frame.done === 'true'))
    assert.ok(initial.frames[0].left > 0 && initial.frames[1].left < 0)
    assert.equal(initial.hidden, undefined); assert.deepEqual(initial.labels, ['Warsaw', 'Warsaw']); assert.ok(initial.frozen)
    proofs.push('synchronous first paint before any ESM HTTP; selected LTR/negative RTL positioning and allowed catalog labels')

    const imported = await page.evaluate(async () => {
        await import('/flex-core-runtime.js')
        await import('/segment-control.js')
        const current = globalThis[Symbol.for('fff.first-paint.segment.v1')]
        document.getElementById('dormant').hidden = false
        const count = current.bootSegmentOverflowElements(document.getElementById('dormant'))
        return { sameSegment: current === window.originalSegment,
            sameTimezone: globalThis[Symbol.for('fff.first-paint.timezone.v1')] === window.originalTimezone,
            count, positioned: document.getElementById('hidden-shell').dataset.ssrScrollPositioned }
    })
    assert.deepEqual(imported, { sameSegment: true, sameTimezone: true, count: 1, positioned: 'true' })
    for (const name of Object.values(owners)) assert.equal(requests.filter(file => file === name).length, 0, 'SSR-installed implementation was downloaded again')
    proofs.push('native ESM reuses the immutable synchronous APIs, does not fetch SSR-installed owners again and positions newly activated dormant shell')

    const morph = await page.evaluate(html => {
        const api = globalThis[Symbol.for('fff.first-paint.segment.v1')]
        const old = document.getElementById('rtl')
        const incoming = document.createElement('div'); incoming.innerHTML = html
        const next = incoming.firstElementChild
        api.preservePositionedSegmentOverflow(old, next)
        api.preserveSegmentPresentation(old.querySelector('[data-segment-selected="true"]'), next.querySelector('[data-segment-selected="true"]'))
        old.replaceWith(next)
        return { marker: next.dataset.ssrScrollPositioned, rebooted: api.positionSegmentOverflowScrollElement(next),
            selected: next.querySelector('[data-segment-selected="true"]').getAttribute('data-segment-selected') }
    }, shell('morphed', 'rtl'))
    assert.deepEqual(morph, { marker: 'true', rebooted: false, selected: 'true' })
    proofs.push('native morph preserves positioned marker and mounted selection without restarting first-position pass')

    const replacement = await page.evaluate(html => {
        const body = document.createElement('body'); body.innerHTML = html
        document.body.replaceWith(body)
        const api = globalThis[Symbol.for('fff.first-paint.segment.v1')]
        return { count: api.bootSegmentOverflowElements(document.body), left: body.firstElementChild.scrollLeft,
            same: api === window.originalSegment, repeat: api.bootSegmentOverflowElements(document.body) }
    }, shell('replacement'))
    assert.equal(replacement.count, 1); assert.ok(replacement.left > 0); assert.equal(replacement.repeat, 0); assert.ok(replacement.same)
    proofs.push('document.body replacement uses retained stateless API and per-element idempotence')
    await page.close()

    const cold = await context.newPage(); cold.on('pageerror', error => errors.push(error.message))
    await cold.goto(`http://127.0.0.1:${server.address().port}/cold`)
    const coldResult = await cold.evaluate(async html => {
        document.body.innerHTML = html // Like a fresh morph: embedded classic scripts are inert.
        const factory = (await import('/segment-control.js')).default
        const element = document.getElementById('cold-shell')
        const instance = factory({ state: '4', optionKeys: Array.from({ length: 8 }, (_, index) => String(index)) })
        instance.$el = element; instance.$refs = { track: element.firstElementChild, scrollShadow: element,
            indicator: element.querySelector('.fff-segment-indicator') }
        instance.$watch = () => {}; instance.$nextTick = callback => callback()
        await instance.init()
        const result = { done: element.dataset.ssrScrollPositioned, left: element.scrollLeft, interactive: instance.segmentOverflowInteractive }
        instance.destroy(); element.remove()
        return result
    }, shell('cold-shell', 'ltr', scripts.segment[0]))
    assert.equal(coldResult.done, 'true'); assert.ok(coldResult.left > 0 && coldResult.interactive)
    proofs.push('cold standalone component import boots an inert Livewire-like fragment without prior SSR owner; factory disposes normally')
    await cold.close()

    const incompatible = await context.newPage()
    await incompatible.goto(`http://127.0.0.1:${server.address().port}/cold`)
    const rejected = await incompatible.evaluate(async () => {
        Object.defineProperty(globalThis, Symbol.for('fff.first-paint.segment.v1'), { value: Object.freeze({ version: 'incompatible' }) })
        try { await import('/segment-control.js'); return false } catch (error) { return /reload required/.test(error.message) }
    })
    assert.ok(rejected); await incompatible.close()
    proofs.push('incompatible document API rejects native ESM rather than silently replacing live implementation')
    assert.deepEqual(errors, [])
    console.log(JSON.stringify({ browser: await browser.version(), actualCompiledOwners: true, proofs, errors }))
} finally {
    await browser?.close(); server.closeAllConnections()
    await new Promise(resolve => server.close(resolve))
}
