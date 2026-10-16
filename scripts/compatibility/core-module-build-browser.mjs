import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import { createServer } from 'node:http'
import { launchCoreBrowser } from './browser-profile.mjs'

// Exercise actual generated ESM, not another bundled copy of the source graph.
const root = path.resolve(import.meta.dirname, '../..')
const dist = path.join(root, 'resources/dist/components')
const manifest = JSON.parse(fs.readFileSync(path.join(dist, 'alpine-manifest.json'), 'utf8'))
const allowed = new Set([...manifest.__entries__.map(name => `${name}.js`), ...manifest.__shared_chunks__])
const requests = [], errors = [], proofs = []
const server = createServer((request, response) => {
    if (request.url === '/') {
        response.writeHead(200, { 'Content-Type': 'text/html', 'Cache-Control': 'no-store' })
        return response.end('<!doctype html><html><head><meta charset="utf-8"></head><body></body></html>')
    }
    const name = request.url.slice(1)
    if (allowed.has(name)) {
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
    const context = await browser.newContext()
    for (const entries of [['flex-rich-editor', 'flex-file-upload'], ['flex-file-upload', 'flex-rich-editor'], ['flex-rich-editor'],
        ['flex-core-runtime', 'flex-runtime-sdk', 'flex-rich-editor', 'flex-file-upload'],
        ['flex-runtime-sdk', 'flex-file-upload', 'flex-core-runtime']]) {
        const page = await context.newPage()
        page.on('pageerror', error => errors.push(error.message))
        const start = requests.length
        await page.goto(`http://127.0.0.1:${server.address().port}/`)
        const result = await page.evaluate(async entries => {
            const result = []
            for (const entry of entries) {
                const module = await import(`/${entry}.js`)
                const again = await import(`/${entry}.js`)
                result.push({ factory: typeof module.default, runtime: typeof module.installCoreRuntime,
                    sdk: module.sdkABI, sameNamespace: module === again })
            }
            return result
        }, entries)
        assert.ok(result.every(item => (item.factory === 'function' || item.runtime === 'function' || item.sdk === 1) && item.sameNamespace))
        const fetched = requests.slice(start)
        const expected = new Set(entries.flatMap(entry => [`${entry}.js`, ...manifest[entry]]))
        // Cold documents have no synchronous SSR APIs. Conditional first-paint
        // owners are required during facade evaluation, never optional UI code.
        for (const name of [...expected]) {
            for (const dependency of manifest.__dynamic_imports__[name] ?? []) {
                if (/^flex-fields-first-paint-(segment|timezone)-/.test(dependency)) expected.add(dependency)
            }
        }
        assert.deepEqual([...new Set(fetched)].sort(), [...expected].sort(), 'Loaded optional code or missed an eager dependency')
        assert.equal(fetched.length, expected.size, 'Browser requested a shared module twice')
        for (const source of ['runtime/lifecycle/resource-scope.js', 'media/lifecycle/owner.js']) {
            const chunks = Object.entries(manifest.__chunk_modules__).filter(([, modules]) => modules.includes(source)).map(([name]) => name)
            assert.equal(chunks.length, 1)
            assert.equal(fetched.filter(name => name === chunks[0]).length, 1)
        }
        proofs.push({ order: entries, requests: fetched.length, eachSharedModuleFetchedOnce: true, optionalModulesFetched: 0 })
        await page.close()
    }
    assert.deepEqual(errors, [])
    console.log(JSON.stringify({ browser: await browser.version(), actualBuiltEntries: true, proofs, errors }))
} finally {
    await browser?.close(); server.closeAllConnections()
    await new Promise(resolve => server.close(resolve))
}
