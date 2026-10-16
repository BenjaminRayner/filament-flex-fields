import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import { promisify } from 'node:util'
import { execFile } from 'node:child_process'
import { createServer } from 'node:http'
import { build } from 'esbuild'
import { launchCoreBrowser } from './browser-profile.mjs'

const root = path.resolve(import.meta.dirname, '../..')
const deliveryProbe = process.env.FLEX_CORE_DELIVERY_PROBE === '1'
const rendered = await promisify(execFile)('php', ['scripts/compatibility/core-render-context.php', '--browser-fixtures', ...(deliveryProbe ? ['--delivery-policy'] : [])], { cwd: root, maxBuffer: 4 * 1024 * 1024 })
assert.ok(rendered.stdout.includes('PASS: core render context acceptance — 7 groups'))
const line = rendered.stdout.split('\n').find(value => value.startsWith('BROWSER_FIXTURES:'))
assert.ok(line, 'Native Blade did not produce browser fixtures.')
const fixture = JSON.parse(Buffer.from(line.slice('BROWSER_FIXTURES:'.length), 'base64').toString())
assert.ok(typeof fixture.alpineModule === 'string' && path.isAbsolute(fixture.alpineModule) && fs.existsSync(fixture.alpineModule), 'Installed Livewire module was not resolved by the native Composer host.')
const alpine = await build({ stdin: { contents: `import { Alpine } from ${JSON.stringify(fixture.alpineModule)}; globalThis.Alpine=Alpine; Alpine.start(); window.fixtureStarted=true`, resolveDir: root }, bundle: true, format: 'iife', minify: true, write: false, logLevel: 'silent' })
const requests = [], errors = [], proofs = []
const send = (response, type, bytes) => { response.writeHead(200, { 'Content-Type': type, 'Cache-Control': 'no-store', ...(deliveryProbe && type === 'text/html' ? { 'Content-Security-Policy': "default-src 'self'; script-src 'self' 'nonce-deliveryProbe123456' 'unsafe-eval'; style-src 'self' 'nonce-deliveryProbe123456'; style-src-attr 'unsafe-inline'" } : {}) }); response.end(bytes) }
const server = createServer((request, response) => {
    const url = new URL(request.url, 'http://localhost')
    if (url.pathname === '/fixture-alpine.js') return send(response, 'text/javascript', alpine.outputFiles[0].contents)
    const mode = url.pathname.slice(1)
    if (Object.hasOwn(fixture.html, mode)) {
        const origin = `http://127.0.0.1:${server.address().port}`
        return send(response, 'text/html', fixture.html[mode].replaceAll('http://localhost', origin).replaceAll('http:\\/\\/localhost', origin))
    }
    if (Object.hasOwn(fixture.files, url.pathname)) {
        requests.push(url.pathname)
        return send(response, url.pathname.endsWith('.css') ? 'text/css' : 'text/javascript', fs.readFileSync(fixture.files[url.pathname]))
    }
    if (url.pathname !== '/favicon.ico') errors.push(`Unknown asset: ${url.pathname}`)
    response.writeHead(404); response.end()
})
let browser
try {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve))
    browser = await launchCoreBrowser({ headless: true })
    const context = await browser.newContext()
    const page = await context.newPage()
    page.setDefaultTimeout(15000)
    page.on('pageerror', error => errors.push(error.message))
    page.on('console', message => { if (message.type() === 'error' && !message.text().includes('404')) errors.push(message.text()) })
    const origin = `http://127.0.0.1:${server.address().port}`
    for (const mode of ['inactive', 'disabled']) {
        requests.length = 0
        await page.goto(`${origin}/${mode}`)
        await page.waitForFunction(() => window.fixtureStarted === true)
        assert.equal(await page.evaluate(() => !!window.__fffFlexFieldAssetInjector || !!globalThis[Symbol.for('fff.core.startup.v1')]), false)
        assert.deepEqual(requests, [], 'Unused panel contacted FLEX assets.')
        proofs.push(`${mode} native panel executes Alpine without FLEX bootstrap, runtime or CSS requests`)
    }
    for (const mode of ['active', 'standalone', 'active']) {
        requests.length = 0
        await page.goto(`${origin}/${mode}`)
        await page.waitForFunction(() => window.__fffFlexFieldAssetInjector && [...document.querySelectorAll('[data-fff-asset-batch]')].every(batch => batch.getAttribute('data-fff-assets-ready') === '')).catch(async error => {
            console.error(JSON.stringify(await page.evaluate(() => ({ installed: !!window.__fffFlexFieldAssetInjector,
                diagnostics: window.__fffFlexFieldAssetInjector?.getDiagnostics?.(),
                markers: [...document.querySelectorAll('[data-fff-asset-batch]')].map(batch => batch.outerHTML),
                dictionaries: document.querySelectorAll('[data-fff-plan-dictionary]').length,
            }))))
            throw error
        })
        const snapshot = await page.evaluate(() => ({
            bootstrap: [...document.scripts].filter(script => script.src.includes('flex-fff-load-bootstrap')).length,
            injector: [...document.scripts].filter(script => script.src.includes('flex-field-asset-injector')).length,
            consumers: document.querySelectorAll('[data-fff-asset-batch]').length,
            styles: [...document.querySelectorAll('link[rel="stylesheet"]')].map(link => link.href),
            diagnostics: window.__fffFlexFieldAssetInjector.getDiagnostics(),
        }))
        assert.equal(snapshot.bootstrap, 1); assert.equal(snapshot.injector, 1); assert.equal(snapshot.consumers, 1)
        assert.ok(snapshot.styles.length >= 2); assert.equal(new Set(snapshot.styles).size, snapshot.styles.length)
        assert.deepEqual(snapshot.diagnostics.records, [], 'Activation recorded a runtime failure.')
        // This facade is an optional SSR modulepreload hint, not the injector entry.
        // Engines may ignore the hint; installation/readiness are asserted above.
        assert.ok(requests.filter(url => url.endsWith('/flex-core-runtime.js')).length <= 1)
        assert.equal(requests.filter(url => url.includes('flex-fff-load-bootstrap')).length, 1)
        assert.equal(requests.filter(url => url.includes('flex-field-asset-injector')).length, 1)
        proofs.push(`${mode} native rendered document starts the actual published ESM runtime once; one independent consumer becomes CSS ready without duplicate links`)
    }
    assert.deepEqual(errors, [])
    console.log(JSON.stringify({ browser: await browser.version(), nativeBladeFixtures: true, compiledDistribution: true, groups: proofs.length, proofs, errors }))
} catch (error) {
    console.error(JSON.stringify({ proofs, requests, errors }))
    throw error
} finally {
    await browser?.close(); server.closeAllConnections()
    await new Promise(resolve => server.close(resolve))
}
