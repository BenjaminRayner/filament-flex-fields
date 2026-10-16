import assert from 'node:assert/strict'
import { execFileSync } from 'node:child_process'
import { createServer } from 'node:http'
import path from 'node:path'
import { build } from 'esbuild'
import { launchCoreBrowser } from './browser-profile.mjs'

const root = path.resolve(import.meta.dirname, '../..')
const fixture = JSON.parse(execFileSync('php', ['scripts/compatibility/core-typed-resources.php'], { cwd: root, encoding: 'utf8' }))
const source = `
import { createFlexFieldAssetInjector } from './resources/js/runtime/asset-runtime.js'
import { createAssetLinkStore } from './resources/js/runtime/assets/asset-link-store.js'
const assert = (ok, message) => { if (!ok) throw Error(message) }
const field = (stylesheets, chunks = [], id = 'probe') => {
    const section = document.createElement('section'), marker = document.createElement('span')
    section.className = 'typed-proof'; marker.hidden = true
    marker.dataset.fffAssetBatch = ''; marker.dataset.fffAssetConsumer = 'probe'; marker.dataset.fffAssetConsumerId = id
    marker.dataset.fffStylesheets = JSON.stringify(stylesheets); marker.dataset.fffChunks = JSON.stringify(chunks)
    section.append(marker); document.body.append(section)
    return { section, marker }
}
window.probeResult = (async () => {
    const fixture = await (await fetch('/fixture')).json(), proofs = []
    const injector = createFlexFieldAssetInjector({ document, window }), graph = injector.getConsumerGraph()
    const modules = field([], [], 'modules'), definition = document.createElement('template')
    definition.dataset.fffPlanDefinition = fixture.id; definition.content.textContent = fixture.plan
    modules.marker.removeAttribute('data-fff-stylesheets'); modules.marker.removeAttribute('data-fff-chunks')
    modules.marker.dataset.fffPlanId = fixture.id; modules.marker.dataset.fffPlanGeneration = '1'
    modules.section.prepend(definition)
    const cdn = field([fixture.cdn + '/theme?edition=cdn&signature=keep-me'], [], 'cdn')
    await injector.ensureAssets(document)
    const inline = document.querySelector('#inline'), dictionary = document.querySelector('#dictionary')
    for (const section of [inline, dictionary, cdn.section]) assert(getComputedStyle(section).color === 'rgb(0, 128, 0)', 'Extensionless stylesheet did not apply')
    assert(getComputedStyle(dictionary).borderTopColor === 'rgb(0, 0, 255)', 'Misleading JS suffix lost CSS kind')
    for (const href of fixture.styles) {
        const url = new URL(href, document.baseURI).href
        assert([...document.querySelectorAll('link')].filter(link => link.href === url && link.rel === 'stylesheet').length === 1, 'Shared CSS transport duplicated')
        assert(![...document.querySelectorAll('link')].some(link => link.href === url && link.rel === 'modulepreload'), 'Stylesheet was also loaded as JS')
        assert(graph.getResourceRefCount({ kind: 'stylesheet', url }) === 2, 'Independent inline/dictionary consumers lost references')
    }
    proofs.push('real PHP inline and dictionary plans share one extensionless/signed stylesheet request with no modulepreload')
    proofs.push('cross-origin CSS retains its kind and query identity regardless of path suffix')
    for (const href of fixture.modules) {
        const url = new URL(href, document.baseURI).href
        assert(![...document.querySelectorAll('link')].some(link => link.href === url && link.rel === 'stylesheet'), 'Module with CSS suffix was loaded as CSS')
        assert((await import(url)).ready === true, 'Typed modulepreload did not populate the native module map')
    }
    proofs.push('extensionless and misleading CSS-suffix modules preload/evaluate as JS and share the native module cache')

    let failed = false
    const invalidClient = createAssetLinkStore({ document, window })
    try { invalidClient.load('/never-request', 'worker') } catch { failed = true } finally { invalidClient.dispose() }
    assert(failed, 'Unknown transport kind did not reject before scheduling')
    proofs.push('unsupported resource kinds fail before link creation or network admission')

    const retry = field(['/retry?signature=unchanged'], [], 'retry')
    failed = false
    try { await injector.ensureAssets(retry.section) } catch { failed = true }
    assert(failed && retry.marker.getAttribute('data-fff-assets-ready') === 'failed', 'Current stylesheet failure lost retry state')
    await injector.ensureAssets(retry.section)
    assert(retry.marker.getAttribute('data-fff-assets-ready') === '', 'Exact-URL retry did not become ready')
    const expired = field(['/sub/app/theme.css?signature=expired'], [], 'expired')
    const peer = field(['/sub/app/theme.css?signature=expired'], [], 'expired-peer')
    const rejected = await Promise.allSettled([injector.ensureAssets(expired.section), injector.ensureAssets(peer.section)])
    assert(rejected.every(result => result.status === 'rejected'), 'Expired authorization was silently admitted')
    expired.marker.dataset.fffStylesheets = '["/sub/app/theme.css?signature=renewed"]'
    await injector.ensureAssets(expired.section)
    assert(expired.marker.getAttribute('data-fff-assets-ready') === '' && peer.marker.getAttribute('data-fff-assets-ready') === 'failed', 'Renewal mutated another owner')
    peer.marker.dataset.fffStylesheets = '["/sub/app/theme.css?signature=renewed"]'
    await injector.ensureAssets(peer.section)
    proofs.push('server-authorized replacement plan renews an expired signed URL per owner in a deployment subdirectory; failed peer stays independent, renewed query is exact and concurrent transport deduplicates')
    proofs.push('failed extensionless CSS retries with unchanged signed URL and current generation readiness')

    // Use a content-negotiated URL to prove kind-specific retirement as well as
    // loading. CSS must retire even while the same URL remains a module demand.
    graph.acquireResources('dual-style', 'probe', [{ kind: 'stylesheet', url: '/dual' }])
    graph.acquireResources('dual-module', 'probe', [{ kind: 'chunk', url: '/dual' }])
    await injector.awaitInflightAssetLoads()
    assert(graph.getResourceRefCount({ kind: 'stylesheet', url: '/dual' }) === 1 && graph.getResourceRefCount({ kind: 'chunk', url: '/dual' }) === 1, 'Same URL kinds merged')
    graph.release('dual-style')
    await new Promise(resolve => setTimeout(resolve, 210))
    assert(![...document.querySelectorAll('link')].some(link => link.href === new URL('/dual', document.baseURI).href && link.rel === 'stylesheet'), 'Retired CSS survived behind a module reference')
    assert([...document.querySelectorAll('link')].some(link => link.href === new URL('/dual', document.baseURI).href && link.rel === 'modulepreload'), 'CSS retirement removed an active module demand')
    const dualUrl = new URL('/dual', document.baseURI).href
    assert((await import(dualUrl)).ready === true, 'Retirement unloaded evaluated/prepared module')
    graph.release('dual-module')
    proofs.push('same-URL CSS and JS demands retire independently while native prepared modules remain usable')

    const dictionaryMarker = dictionary.querySelector('[data-fff-asset-batch]')
    graph.release(graph.resolveConsumerInstanceId(dictionaryMarker))
    dictionary.remove()
    await new Promise(resolve => setTimeout(resolve, 210))
    assert(inline.querySelector('[data-fff-asset-batch]').getAttribute('data-fff-assets-ready') === '', 'Closing one owner invalidated the other')
    assert(getComputedStyle(inline).color === 'rgb(0, 128, 0)', 'Shared CSS retired before its last owner')
    proofs.push('removing one repeated owner preserves the other CSS and readiness')
    injector.dispose()
    window.probeFinished = true
    return proofs
})().catch(error => { window.probeFailure = error.message; throw error })
`
const output = await build({ stdin: { contents: source, resolveDir: root }, bundle: true, format: 'esm', minify: true, write: false, loader: { '.svg': 'text' }, logLevel: 'silent' })
const requests = [], cdnRequests = []
let retryAttempts = 0
const respond = (response, type, body, status = 200) => {
    response.writeHead(status, { 'Content-Type': type, 'Cache-Control': status === 200 ? 'public, max-age=31536000' : 'no-store', 'Access-Control-Allow-Origin': '*' })
    response.end(body)
}
const cdn = createServer((request, response) => {
    cdnRequests.push({ url: request.url, destination: request.headers['sec-fetch-dest'] })
    respond(response, 'text/css', '.typed-proof { color: rgb(0, 128, 0) }')
})
const server = createServer((request, response) => {
    const url = request.url, destination = request.headers['sec-fetch-dest']
    requests.push({ url, destination })
    if (url === '/') return respond(response, 'text/html', '<!doctype html><html><head><meta charset="utf-8"></head><body>' + fixture.inline + fixture.dictionary + '<script type="module" src="/probe.js"></script></body></html>')
    if (url === '/probe.js') return respond(response, 'text/javascript', output.outputFiles[0].contents)
    if (url === '/fixture') return respond(response, 'application/json', JSON.stringify({ ...fixture, cdn: 'http://127.0.0.1:' + cdn.address().port }))
    if (url === fixture.styles[0]) return respond(response, 'text/css', '.typed-proof { color: rgb(0, 128, 0) }')
    if (url === fixture.styles[1]) return respond(response, 'text/css', '.typed-proof { border-top: 1px solid rgb(0, 0, 255) }')
    if (fixture.modules.includes(url)) return respond(response, 'text/javascript', 'export const ready = true')
    if (url === '/dual') {
        response.setHeader('Vary', 'Sec-Fetch-Dest')
        return respond(response, destination === 'style' ? 'text/css' : 'text/javascript', destination === 'style' ? '.dual { color: green }' : 'export const ready = true')
    }
    if (url === '/retry?signature=unchanged') return respond(response, 'text/css', retryAttempts++ === 0 ? '' : '.typed-proof { outline-color: green }', retryAttempts === 1 ? 503 : 200)
    if (url === '/sub/app/theme.css?signature=expired') return respond(response, 'text/css', '', 403)
    if (url === '/sub/app/theme.css?signature=renewed') return respond(response, 'text/css', '.typed-proof { border-bottom-color: green }')
    response.writeHead(404); response.end()
})
let browser
try {
    await new Promise(resolve => cdn.listen(0, '127.0.0.1', resolve))
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve))
    browser = await launchCoreBrowser({ headless: true })
    const page = await browser.newPage(), errors = []
    page.on('pageerror', error => errors.push(error.message))
    await page.goto('http://127.0.0.1:' + server.address().port + '/', { waitUntil: 'domcontentloaded' })
    await page.waitForFunction(() => window.probeFailure || window.probeFinished, null, { timeout: 5000 })
    const failure = await page.evaluate(() => window.probeFailure)
    if (failure) throw Error(JSON.stringify({ failure, requests, cdnRequests }))
    const proofs = await page.evaluate(() => window.probeResult)
    assert.equal(proofs.length, 8)
    assert.deepEqual(errors, [])
    for (const url of fixture.styles) {
        assert.deepEqual(requests.filter(request => request.url === url).map(request => request.destination), ['style'])
    }
    for (const url of fixture.modules) assert.equal(requests.filter(request => request.url === url).length, 1)
    assert.equal(requests.some(request => request.url === '/never-request'), false)
    assert.equal(requests.filter(request => request.url.startsWith('/retry?')).length, 2)
    assert.equal(requests.filter(request => request.url === '/sub/app/theme.css?signature=expired').length, 1)
    assert.equal(requests.filter(request => request.url === '/sub/app/theme.css?signature=renewed').length, 1)
    assert.deepEqual(requests.filter(request => request.url === '/dual').map(request => request.destination).sort(), ['script', 'style'])
    assert.deepEqual(cdnRequests.map(request => request.destination), ['style'])
    console.log(JSON.stringify({ browser: await browser.version(), minifiedSourceBuild: true, proofs, requests, cdnRequests, errors }))
} finally {
    await browser?.close()
    server.closeAllConnections(); cdn.closeAllConnections()
    await Promise.all([new Promise(resolve => server.close(resolve)), new Promise(resolve => cdn.close(resolve))])
}
