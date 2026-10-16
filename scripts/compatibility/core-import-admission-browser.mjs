import assert from 'node:assert/strict'
import { createServer } from 'node:http'
import path from 'node:path'
import { build } from 'esbuild'
import { launchCoreBrowser, browserEngine } from './browser-profile.mjs'

const root = path.resolve(import.meta.dirname, '../..')
const output = await build({ stdin: { resolveDir: root, contents: `
import { importAdmittedModule } from './resources/js/runtime/assets/module-admission.js'
import { assetAdmissionSnapshot } from './resources/js/runtime/assets/shared-admission.js'
import { createAssetLinkStore } from './resources/js/runtime/assets/asset-link-store.js'
window.admission = { load: importAdmittedModule, snapshot: () => assetAdmissionSnapshot(document), store: () => createAssetLinkStore({ document, window }) }
` }, bundle: true, format: 'esm', minify: true, write: false, logLevel: 'silent' })
const requests = []
const retries = new Map()
const server = createServer((request, response) => {
    requests.push(request.url)
    const respond = (type, body, status = 200) => {
        response.writeHead(status, { 'Content-Type': type, 'Cache-Control': status === 200 ? 'public, max-age=31536000' : 'no-store' }); response.end(body)
    }
    if (request.url === '/') return respond('text/html', '<!doctype html><body><script type="module" src="/probe.js"></script>')
    if (request.url === '/probe.js') return respond('text/javascript', output.outputFiles[0].contents)
    if (request.url.startsWith('/hold/')) {
        const id = JSON.stringify(request.url)
        return respond('text/javascript', `window.nativeStarted.push(${id}); await new Promise(resolve => window.nativeRelease.set(${id}, resolve)); export const ready = ${id}`)
    }
    if (request.url === '/style') return respond('text/css', 'body { color: rgb(0, 128, 0) }')
    if (['/retry?signed=unchanged', '/retry?signed=native-reference'].includes(request.url)) {
        const attempts = (retries.get(request.url) ?? 0) + 1
        retries.set(request.url, attempts)
        return respond('text/javascript', attempts === 1 ? '' : 'export const ready = true', attempts === 1 ? 503 : 200)
    }
    if (request.url === '/evaluation-failure') return respond('text/javascript', 'window.failedEvaluations = (window.failedEvaluations ?? 0) + 1; throw Error("native evaluation failure"); export const ready = false')
    response.writeHead(404); response.end()
})
let browser
try {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve))
    browser = await launchCoreBrowser({ headless: true })
    const page = await browser.newPage(), errors = [], proofs = []
    page.on('pageerror', error => errors.push(error.message))
    await page.goto(`http://127.0.0.1:${server.address().port}/`, { waitUntil: 'domcontentloaded' })
    await page.waitForFunction(() => window.admission)
    await page.evaluate(() => {
        window.nativeStarted = []; window.nativeRelease = new Map(); window.store = admission.store()
        window.owner = new AbortController()
        window.first = admission.load('/hold/1', { signal: owner.signal }).catch(error => error.name)
        window.other = admission.load('/hold/1')
        window.batch = [other, ...Array.from({ length: 5 }, (_, i) => admission.load('/hold/' + (i + 2)))]
        window.queued = admission.load('/hold/7')
        const cancelled = new AbortController()
        window.cancelled = admission.load('/never-start', { signal: cancelled.signal }).catch(error => error.name)
        cancelled.abort()
        window.oldStyle = store.load('/old-style', 'stylesheet').catch(error => error.name)
        owner.abort()
    })
    await page.waitForFunction(() => nativeStarted.length === 6)
    assert.equal(await page.evaluate(() => first), 'AbortError')
    assert.equal(await page.evaluate(() => cancelled), 'AbortError')
    assert.equal(await page.evaluate(() => admission.snapshot().active), 6)
    assert.equal(requests.includes('/hold/7'), false); assert.equal(requests.includes('/never-start'), false)
    assert.equal(requests.includes('/old-style'), false)
    proofs.push('six actual native evaluations share a bounded queue with link transport; one detached owner preserves the shared import; queued detached entry has zero network requests')
    await page.evaluate(() => { store.dispose(); window.replacement = admission.store(); window.style = replacement.load('/style', 'stylesheet') })
    assert.equal(await page.evaluate(() => oldStyle), 'AbortError')
    assert.equal(await page.evaluate(() => admission.snapshot().active), 6)
    assert.equal(requests.includes('/style'), false)
    proofs.push('runtime/store replacement preserves running native admissions and cancels only its queued link requests')
    await page.evaluate(() => nativeRelease.get('/hold/1')())
    await page.waitForFunction(() => nativeStarted.length === 7)
    await page.evaluate(() => { for (const release of nativeRelease.values()) release() })
    await page.evaluate(() => Promise.all([...batch, queued, style]))
    assert.equal(await page.evaluate(() => getComputedStyle(document.body).color), 'rgb(0, 128, 0)')
    assert.equal(await page.evaluate(() => admission.snapshot().active), 0)
    assert.equal(requests.filter(url => url === '/hold/1').length, 1)
    await page.evaluate(() => admission.load('/hold/1'))
    assert.equal(requests.filter(url => url === '/hold/1').length, 1)
    proofs.push('queued stylesheet applies and evaluation completes without deadlock; repeat native import keeps browser module cache and one fetch')
    const nativeRetry = await page.evaluate(async () => {
        const outcomes = []
        for (let i = 0; i < 2; i++) {
            try { outcomes.push((await admission.load('/retry?signed=unchanged')).ready === true) }
            catch { outcomes.push(false) }
            if (admission.snapshot().active !== 0) throw Error('Failed native import retained admission')
        }
        return outcomes
    })
    // Failed network imports differ between browser releases. Compare the
    // coordinator with native import in this engine instead of pinning old behavior.
    const referenceRetry = await page.evaluate(async () => {
        const outcomes = []
        for (let i = 0; i < 2; i++) {
            try { outcomes.push((await import('/retry?signed=native-reference')).ready === true) }
            catch { outcomes.push(false) }
        }
        return outcomes
    })
    assert.equal(referenceRetry[0], false)
    assert.deepEqual(nativeRetry, referenceRetry)
    const nativeRetryRequests = requests.filter(url => url === '/retry?signed=unchanged').length
    assert.equal(nativeRetryRequests, requests.filter(url => url === '/retry?signed=native-reference').length)
    assert.equal(nativeRetryRequests, referenceRetry[1] ? 2 : 1)
    proofs.push(`native ${browserEngine} failed-load behavior is preserved (${nativeRetry.join(',')}); each attempt releases admission without URL rewriting or a universal refetch guarantee`)
    await page.evaluate(async () => {
        for (let i = 0; i < 2; i++) {
            try { await admission.load('/evaluation-failure'); throw Error('Unexpected successful evaluation') }
            catch (error) { if (!error.message.includes('native evaluation failure')) throw error }
        }
        if (failedEvaluations !== 1) throw Error('Native evaluation failure cache was bypassed')
        replacement.dispose()
        if (admission.snapshot() !== null) throw Error('Coordinator retained after all owners and flights finished')
    })
    assert.equal(requests.filter(url => url === '/evaluation-failure').length, 1)
    proofs.push('evaluation failures retain native cache semantics; final owner disposal removes admission metadata')
    await page.reload({ waitUntil: 'domcontentloaded' })
    await page.waitForFunction(() => window.admission)
    assert.equal(await page.evaluate(async () => (await admission.load('/retry?signed=unchanged')).ready), true)
    const afterReloadRequests = requests.filter(url => url === '/retry?signed=unchanged').length
    assert(afterReloadRequests >= nativeRetryRequests && afterReloadRequests <= nativeRetryRequests + 1, 'Fresh document bypassed native cache or fetched the signed entry more than once')
    proofs.push('a fresh document loads the recovered module using the exact same signed URL')
    assert.deepEqual(errors, [])
    console.log(JSON.stringify({ browser: await browser.version(), minifiedSourceBuild: true, proofs, requests, errors }))
} finally {
    await browser?.close(); server.closeAllConnections()
    await new Promise(resolve => server.close(resolve))
}
