import assert from 'node:assert/strict'
import { createServer } from 'node:http'
import { createHash } from 'node:crypto'
import { build } from 'esbuild'
import { launchCoreBrowser } from './browser-profile.mjs'
import path from 'node:path'

const root = path.resolve(import.meta.dirname, '../..')
const bundle = await build({ stdin: { resolveDir: root, contents: `
import { createAssetLinkStore } from './resources/js/runtime/assets/asset-link-store.js'
import { importAdmittedModule } from './resources/js/runtime/assets/module-admission.js'
import { decodeDeliveryPolicy, assertAssetDelivery } from './resources/js/runtime/assets/delivery-policy.js'
import { assetAdmissionSnapshot } from './resources/js/runtime/assets/shared-admission.js'
window.delivery = { store: () => createAssetLinkStore({ document, window }), load: importAdmittedModule,
  decode: decodeDeliveryPolicy, check: url => assertAssetDelivery(document, url), snapshot: () => assetAdmissionSnapshot(document) }
` }, bundle: true, format: 'esm', minify: true, write: false, logLevel: 'silent' })
const sri = body => 'sha256-' + createHash('sha256').update(body).digest('base64')
const css = 'body { color: rgb(0, 128, 0) }'
const module = 'window.verifiedEvaluations = (window.verifiedEvaluations ?? 0) + 1; export const ready = true'
const nonce = 'documentNonce123456'
const escape = raw => raw.replaceAll('&', '&amp;').replaceAll('"', '&quot;').replaceAll('<', '&lt;')
const requests = [], cdnRequests = [], proofs = []
let origin, cdnOrigin
const send = (response, type, body, headers = {}, status = 200) => {
    response.writeHead(status, { 'Content-Type': type, 'Cache-Control': 'no-store', ...headers }); response.end(body)
}
const cdn = createServer((request, response) => {
    cdnRequests.push({ url: request.url, referrer: request.headers.referer ?? null, cookie: request.headers.cookie ?? null })
    send(response, 'text/css', css, request.url === '/cors-denied.css' ? {} : { 'Access-Control-Allow-Origin': '*' })
})
const server = createServer((request, response) => {
    requests.push({ url: request.url, referrer: request.headers.referer ?? null })
    if (request.url === '/') {
        const policy = { format: 1, allowedOrigins: [cdnOrigin], crossorigin: 'anonymous', referrerPolicy: 'no-referrer', integrity: {
            '/valid.css?signature=unchanged': sri(css), '/bad.css': sri(css), '/valid-module.js': sri(module), '/bad-module.js': sri(module),
            '/ssr.css': sri(css), '/wrong-mime.js': sri(module), [cdnOrigin + '/valid.css']: sri(css), [cdnOrigin + '/cors-denied.css']: sri(css),
        } }
        return send(response, 'text/html', `<!doctype html><head>
<link rel="stylesheet" href="/ssr.css" data-fff-managed-asset="true" integrity="${sri(css)}" crossorigin="anonymous" referrerpolicy="no-referrer" nonce="${nonce}">
<script type="module" src="/probe.js" nonce="${nonce}" data-fff-delivery-policy="${escape(JSON.stringify(policy))}"></script></head><body>delivery probe</body>`, {
            'Content-Security-Policy': `default-src 'none'; script-src 'self' 'nonce-${nonce}'; style-src 'nonce-${nonce}'; connect-src 'self' ${cdnOrigin}`,
        })
    }
    if (request.url === '/required') {
        const policy = { format: 1, allowedOrigins: [], crossorigin: null, referrerPolicy: null, requireIntegrity: true, integrity: {} }
        return send(response, 'text/html', `<!doctype html><script type="module" src="/probe.js" data-fff-delivery-policy="${escape(JSON.stringify(policy))}"></script>`)
    }
    if (['/cached-preload', '/importmap'].includes(request.url)) {
        const expected = sri('export const ready = true')
        const policy = { format: 1, allowedOrigins: [], crossorigin: null, referrerPolicy: null, integrity: { '/cached-module.js': expected } }
        const importMap = request.url === '/importmap' ? `<script type="importmap">${JSON.stringify({ integrity: { '/cached-module.js': expected } })}</script>` : ''
        return send(response, 'text/html', `<!doctype html><head>${importMap}<script type="module" src="/cache-wrapper.js" data-fff-delivery-policy="${escape(JSON.stringify(policy))}"></script></head><body>module cache boundary</body>`)
    }
    if (request.url === '/cache-wrapper.js') return send(response, 'text/javascript', 'import "/cached-module.js"; import "/probe.js";')
    if (request.url === '/cached-module.js') return send(response, 'text/javascript', 'window.cachedUnverifiedModule = true; export const ready = true')
    if (request.url === '/probe.js') return send(response, 'text/javascript', bundle.outputFiles[0].contents)
    if (['/valid.css?signature=unchanged', '/ssr.css'].includes(request.url)) return send(response, 'text/css', css)
    if (request.url === '/bad.css') return send(response, 'text/css', 'body { color: red }')
    if (request.url === '/valid-module.js') return send(response, 'text/javascript', module)
    if (request.url === '/bad-module.js') return send(response, 'text/javascript', 'window.unverifiedExecuted = true; export const ready = true')
    if (request.url === '/wrong-mime.js') return send(response, 'text/plain', module)
    if (request.url === '/independent.css') return send(response, 'text/css', 'body { background-color: rgb(0, 128, 0) }')
    if (request.url === '/offline.css?signature=retained') return send(response, 'text/css', '.offline-proof { color: green }')
    response.writeHead(404); response.end()
})
let browser
try {
    await new Promise(resolve => cdn.listen(0, '127.0.0.1', resolve))
    cdnOrigin = `http://127.0.0.1:${cdn.address().port}`
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve))
    origin = `http://127.0.0.1:${server.address().port}`
    browser = await launchCoreBrowser({ headless: true })
    const page = await browser.newPage(), errors = []
    page.on('pageerror', error => errors.push(error.message))
    await page.goto(origin + '/', { waitUntil: 'domcontentloaded' })
    await page.waitForFunction(() => window.delivery)
    await page.waitForFunction(() => document.querySelector('link[href="/ssr.css"]').sheet !== null)
    const ssrRequestsBeforeAdoption = requests.filter(r => r.url === '/ssr.css').length
    await page.evaluate(async () => {
        window.store = delivery.store()
        await store.load('/ssr.css', 'stylesheet')
        await Promise.all([store.load('/valid.css?signature=unchanged', 'stylesheet'), store.load('/valid.css?signature=unchanged', 'stylesheet')])
    })
    assert.equal(requests.filter(r => r.url === '/ssr.css').length, ssrRequestsBeforeAdoption, 'FLEX adoption restarted the already loaded SSR stylesheet')
    assert(ssrRequestsBeforeAdoption > 0)
    assert.equal(requests.filter(r => r.url === '/valid.css?signature=unchanged').length, 1)
    assert.equal(requests.find(r => r.url === '/valid.css?signature=unchanged').referrer, null)
    assert.equal(await page.evaluate(() => getComputedStyle(document.body).color), 'rgb(0, 128, 0)')
    proofs.push(`real CSP nonce and SHA-256 stylesheet verification; SSR adoption adds zero requests to the native parser baseline (${ssrRequestsBeforeAdoption}); simultaneous FLEX owners share one fetch with exact signed query and no referrer`)
    const badStylesheet = await page.evaluate(async () => { try { await store.load('/bad.css', 'stylesheet'); return null } catch (error) { return error.message } })
    assert.match(badStylesheet, /failed to load/)
    await page.evaluate(() => store.load('/independent.css', 'stylesheet'))
    assert.equal(await page.evaluate(() => getComputedStyle(document.body).backgroundColor), 'rgb(0, 128, 0)')
    proofs.push('tampered stylesheet rejected by the browser; independent stylesheet remains ready without a global failure')
    await page.evaluate(async () => {
        const result = await Promise.all([delivery.load('/valid-module.js'), delivery.load('/valid-module.js')])
        if (!result.every(module => module.ready) || verifiedEvaluations !== 1) throw Error('Verified module was duplicated.')
        store.dispose(); window.store = delivery.store()
        await delivery.load('/valid-module.js')
        if (verifiedEvaluations !== 1) throw Error('Warm module map was not reused.')
    })
    assert.equal(requests.filter(r => r.url === '/valid-module.js').length, 1)
    const moduleFailures = await page.evaluate(async () => {
        const results = []
        for (const url of ['/bad-module.js', '/wrong-mime.js']) {
            try { await delivery.load(url); results.push('unexpected success') } catch (error) { results.push(error.message) }
        }
        if (window.unverifiedExecuted) throw Error('Unverified module was evaluated.')
        return results
    })
    moduleFailures.forEach(message => assert.match(message, /failed to load/))
    proofs.push('SRI-gated native module script verifies bytes before evaluation, once across remount; modified bytes and wrong MIME never evaluate')
    await page.evaluate(async cdnUrl => store.load(cdnUrl + '/valid.css', 'stylesheet'), cdnOrigin)
    const corsFailure = await page.evaluate(async cdnUrl => { try { await store.load(cdnUrl + '/cors-denied.css', 'stylesheet'); return null } catch (error) { return error.message } }, cdnOrigin)
    assert.match(corsFailure, /failed to load/)
    assert(cdnRequests.every(request => request.referrer === null && request.cookie === null))
    const forbidden = 'https://unlisted.example.test/forbidden.css'
    const forbiddenResults = await page.evaluate(async url => {
        const result = []
        for (const work of [() => store.load(url, 'stylesheet'), () => delivery.load(url), () => delivery.load('https://user:password@evil.test/x.js')]) {
            try { await work(); result.push(null) } catch (error) { result.push(error.code) }
        }
        return result
    }, forbidden)
    assert.deepEqual(forbiddenResults, ['asset_policy_failed', 'asset_policy_failed', 'asset_policy_failed'])
    proofs.push('exact origin/port allowlist protects both links and imports; CDN is anonymous and referrer-free; CORS denial fails locally; credentials rejected before transport')
    const mismatch = await page.evaluate(async () => {
        const link = document.querySelector('link[href="/ssr.css"]')
        link.setAttribute('integrity', 'sha256-' + 'A'.repeat(43) + '=')
        try { await store.load('/ssr.css', 'stylesheet'); return null } catch (error) { return error.code }
    })
    assert.equal(mismatch, 'asset_policy_failed')
    const malformed = await page.evaluate(() => {
        const base = { format: 1, allowedOrigins: [], crossorigin: null, referrerPolicy: null, integrity: {} }
        return [ { ...base, crossorigin: 'use-credentials' }, { ...base, allowedOrigins: ['https://*.example.test'] },
            { ...base, integrity: { '/x': 'broken' } }, { ...base, extra: true }, { ...base, allowedOrigins: ['https://cdn.test/path'] },
        ].map(policy => { try { delivery.decode(JSON.stringify(policy), document.baseURI); return null } catch (error) { return error.code } })
    })
    assert(malformed.every(code => code === 'asset_policy_failed'))
    const nonceFailures = await page.evaluate(() => {
        const raw = JSON.stringify({ format: 1, allowedOrigins: [], crossorigin: null, referrerPolicy: null, integrity: {} })
        return ['documentNonce123456\n', 'documentNonce123456\r'].map(nonce => {
            try { delivery.decode(raw, document.baseURI, nonce); return null } catch (error) { return error.code }
        })
    })
    assert.deepEqual(nonceFailures, ['asset_policy_failed', 'asset_policy_failed'])
    await page.context().setOffline(true)
    assert.match(await page.evaluate(async () => {
        try { await store.load('/offline.css?signature=retained', 'stylesheet'); return '' } catch (error) { return error.message }
    }), /failed to load/)
    await page.context().setOffline(false)
    await page.evaluate(() => Promise.all([store.load('/offline.css?signature=retained', 'stylesheet'), store.load('/offline.css?signature=retained', 'stylesheet')]))
    assert.equal(requests.filter(request => request.url === '/offline.css?signature=retained').length, 1)
    proofs.push('offline stylesheet admission fails locally, then concurrent retry uses one exact signed URL; nonce rejects trailing newline before any link admission')
    await page.evaluate(() => store.dispose())
    assert.equal(await page.evaluate(() => delivery.snapshot()?.active ?? 0), 0)
    const rejectedModuleDiagnostics = errors.filter(message => message === 'Cannot load script due to integrity mismatch'
        || (message.includes("'text/plain' is not a valid JavaScript MIME type") && message.includes('/wrong-mime.js')))
    assert.deepEqual(errors.filter(message => !rejectedModuleDiagnostics.includes(message)), [])
    errors.length = 0
    const cachedPage = await browser.newPage()
    await cachedPage.goto(origin + '/cached-preload', { waitUntil: 'networkidle' })
    await cachedPage.waitForFunction(() => window.delivery)
    const cachedBoundary = await cachedPage.evaluate(async () => {
        if (!window.cachedUnverifiedModule) throw Error('Cache fixture did not execute its static import.')
        try { await delivery.load('/cached-module.js'); return 'module-map-reused' } catch { return 'preload-rejected' }
    })
    assert(['module-map-reused', 'preload-rejected'].includes(cachedBoundary))
    await cachedPage.close()
    const mappedPage = await browser.newPage()
    await mappedPage.goto(origin + '/importmap', { waitUntil: 'networkidle' })
    assert.equal(await mappedPage.evaluate(() => !!window.cachedUnverifiedModule), false)
    assert.equal(await mappedPage.evaluate(() => !!window.delivery), false)
    await mappedPage.close()
    const requiredPage = await browser.newPage()
    await requiredPage.goto(origin + '/required', { waitUntil: 'domcontentloaded' })
    await requiredPage.waitForFunction(() => window.delivery)
    const unsigned = await requiredPage.evaluate(async () => {
        const store = delivery.store(), results = []
        for (const work of [() => store.load('/never-unsigned.css', 'stylesheet'), () => delivery.load('/never-unsigned.js')]) {
            try { await work(); results.push(null) } catch (error) { results.push(error.code) }
        }
        store.dispose(); return results
    })
    assert.deepEqual(unsigned, ['asset_policy_failed', 'asset_policy_failed'])
    assert(!requests.some(request => request.url.startsWith('/never-unsigned')))
    await requiredPage.close()
    proofs.push('required integrity blocks unknown same-origin CSS and ESM before any network or evaluation')
    proofs.push('the initial import-map fixture blocks modified static dependency evaluation in this engine; already imported foreign modules cannot be retroactively attested (' + cachedBoundary + ')')
    proofs.push('conflicting loaded-link metadata fails closed; malformed policies fail before admission; final disposal releases active transport without a page exception')
    console.log(JSON.stringify({ browser: browser.version(), minifiedSourceBuild: true, proofs, rejectedModuleDiagnostics, wholeStackStrictCsp: false, transitiveImportIntegrity: false, errors }))
} finally {
    await browser?.close()
    await Promise.all([new Promise(resolve => server.close(resolve)), new Promise(resolve => cdn.close(resolve))])
}
