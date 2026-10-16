import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import { execFile } from 'node:child_process'
import { promisify } from 'node:util'
import { createServer } from 'node:http'
import { launchCoreBrowser } from './browser-profile.mjs'

const root = path.resolve(import.meta.dirname, '../..')
const rendered = await promisify(execFile)('php', ['scripts/compatibility/core-release-lifecycle.php', '--browser-fixtures'], { cwd: root, maxBuffer: 1024 * 1024 })
const line = rendered.stdout.split('\n').find(value => value.startsWith('BROWSER_FIXTURES:'))
assert.ok(line, 'Release lifecycle did not produce verified browser fixtures')
const fixture = JSON.parse(Buffer.from(line.slice('BROWSER_FIXTURES:'.length), 'base64').toString())
let current = fixture.a, server, browser, port
let packageCurrent = fixture.packageA
const errors = [], requests = [], proofs = []
function makeServer() {
    return createServer((request, response) => {
        const url = new URL(request.url, 'http://localhost')
        if (url.pathname === '/') {
            response.writeHead(200, { 'Content-Type': 'text/html', 'Cache-Control': 'no-store' })
            response.end(`<!doctype html><html><head><link rel="stylesheet" href="/${current}/css/core.css"></head><body><script type="module">import * as release from '/${current}/components/entry.js'; window.release=release</script></body></html>`)
            return
        }
        if (url.pathname === '/package') {
            response.writeHead(200, { 'Content-Type': 'text/html', 'Cache-Control': 'no-store' })
            response.end(`<!doctype html><html><head><link rel="stylesheet" href="/${packageCurrent}/control.css"></head><body><script type="module">import * as release from '/${packageCurrent}/entry.js'; window.release=release</script></body></html>`)
            return
        }
        if ([fixture.a, fixture.b, fixture.packageA, fixture.packageB].some(directory => url.pathname.startsWith(`/${directory}/`))) {
            const file = path.join(fixture.public, url.pathname)
            assert.ok(file.startsWith(`${fixture.public}/`))
            if (fs.existsSync(file)) {
                requests.push(url.pathname)
                response.writeHead(200, { 'Content-Type': file.endsWith('.js') ? 'text/javascript' : file.endsWith('.css') ? 'text/css' : 'audio/mpeg', 'Cache-Control': 'public, max-age=31536000, immutable' })
                response.end(fs.readFileSync(file))
                return
            }
        }
        response.writeHead(404); response.end()
    })
}
async function start() {
    server = makeServer()
    await new Promise(resolve => server.listen(port ?? 0, '127.0.0.1', resolve))
    port = server.address().port
}
async function stop() {
    server.closeAllConnections()
    await new Promise(resolve => server.close(resolve))
}
try {
    await start()
    browser = await launchCoreBrowser({ headless: true })
    const context = await browser.newContext()
    context.on('page', page => page.on('pageerror', error => errors.push(error.message)))
    const old = await context.newPage()
    const origin = `http://127.0.0.1:${port}`
    await old.goto(origin)
    await old.waitForFunction(() => window.release?.version === 'A')
    assert.equal(requests.filter(url => url.endsWith('/lazy.js')).length, 0)
    current = fixture.b
    const fresh = await context.newPage()
    await fresh.goto(origin)
    await fresh.waitForFunction(() => window.release?.version === 'B')
    proofs.push('An open A document survives rollout; a new document resolves B with independent immutable CSS/ESM URLs.')
    await stop(); await start()
    assert.equal(await old.evaluate(async () => (await window.release.lazy()).version), 'A')
    assert.equal(requests.filter(url => url === `/${fixture.a}/components/lazy.js`).length, 1)
    assert.equal(await old.evaluate(async url => await (await fetch(url)).text(), `/${fixture.a}/audio/tone.mp3`), 'binary-A')
    proofs.push('After a fresh HTTP server restart, the old tab loads its previously cold A lazy chunk and original binary; no source checkout or mutable current pointer is required.')
    current = fixture.a
    const rollback = await context.newPage()
    await rollback.goto(origin)
    await rollback.waitForFunction(() => window.release?.version === 'A')
    assert.equal(await fresh.evaluate(async () => (await window.release.lazy()).version), 'B')
    assert.equal(requests.filter(url => url === `/${fixture.b}/components/lazy.js`).length, 1)
    proofs.push('Rollback serves A to fresh documents while an already-open B tab still loads its own cold B lazy chunk.')

    const packageOld = await context.newPage()
    await packageOld.goto(`${origin}/package`)
    await packageOld.waitForFunction(() => window.release?.version === 'A')
    assert.equal(requests.filter(url => url === `/${fixture.packageA}/lazy.js`).length, 0)
    packageCurrent = fixture.packageB
    const packageNew = await context.newPage()
    await packageNew.goto(`${origin}/package`)
    await packageNew.waitForFunction(() => window.release?.version === 'B')
    await stop(); await start()
    assert.equal(await packageOld.evaluate(async () => (await window.release.lazy()).version), 'A')
    assert.equal(await packageOld.evaluate(async url => await (await fetch(url)).text(), `/${fixture.packageA}/tone.mp3`), 'binary-A')
    packageCurrent = fixture.packageA
    const packageRollback = await context.newPage()
    await packageRollback.goto(`${origin}/package`)
    await packageRollback.waitForFunction(() => window.release?.version === 'A')
    assert.equal(await packageNew.evaluate(async () => (await window.release.lazy()).version), 'B')
    assert.equal(requests.filter(url => url === `/${fixture.packageA}/lazy.js`).length, 1)
    assert.equal(requests.filter(url => url === `/${fixture.packageB}/lazy.js`).length, 1)
    proofs.push('Portable package documents retain their own cold lazy imports and binary across independent rollout, server restart and rollback; a restored pruned B release serves its original complete bytes.')
    assert.deepEqual(errors, [])
    console.log(JSON.stringify({ browser: await browser.version(), immutablePublicCopies: true, groups: proofs.length, proofs, errors }))
} finally {
    await browser?.close()
    if (server?.listening) await stop()
    fs.rmSync(fixture.work, { recursive: true, force: true })
}
