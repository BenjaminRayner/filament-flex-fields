import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import { createServer } from 'node:http'
import { build } from 'esbuild'
import { launchCoreBrowser } from './browser-profile.mjs'
import { createFlexSdkPlugin } from '../assets/flex-sdk-plugin.mjs'

// The synchronous proxy was not covered by the existing full-directive tests.
// Exercise the actual distribution and installed Alpine with controlled HTTP.
const root = path.resolve(import.meta.dirname, '../..')
const alpine = await build({ stdin: { contents: `import { Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm.js'; globalThis.Alpine = Alpine; Alpine.start(); window.alpineStarted = true`, resolveDir: root }, bundle: true, format: 'iife', minify: true, write: false, logLevel: 'silent' })
const gateModule = await build({ entryPoints: [path.join(root, 'resources/js/core/startup/startup-gate.js')], bundle: true, format: 'esm', minify: true, write: false, logLevel: 'silent' })
const author = await build({ stdin: { contents: `
import { createResourceScope, isAssetElementActive, waitForAssetActivation } from '@bjanczak/flex-fields/sdk/v1';
export default id => ({ id, scope: null,
    init() { this.scope = createResourceScope(); this.scope.defer(() => window.destroyed.push(id));
        this.scope.listen(this.$el, 'author-effect', () => window.authorEffects.push(id));
        if (!isAssetElementActive(this.$el)) throw Error('Inactive author factory');
        window.initialized.push(id); return waitForAssetActivation(this.$el); },
    destroy() { this.scope.dispose(); }
})`, resolveDir: root }, absWorkingDir: root, bundle: true, format: 'esm', minify: true, metafile: true, plugins: [createFlexSdkPlugin()], write: false, logLevel: 'silent' })
assert.ok(Object.hasOwn(author.metafile.inputs, 'fff-runtime-sdk:v1'))
assert.ok(Object.keys(author.metafile.inputs).every(input => !input.includes('resources/js/')))
const runtimePath = '/dist/components/flex-core-runtime.js'
const manifest = JSON.parse(fs.readFileSync(path.join(root, 'resources/dist/components/alpine-manifest.json')))
const allowed = new Set([...manifest.__entries__.map(name => `${name}.js`), ...manifest.__shared_chunks__])
const requests = [], errors = [], proofs = [], waiting = new Map(), released = new Set()
const respond = (res, type, body) => { res.writeHead(200, { 'Content-Type': type, 'Cache-Control': 'no-store' }); res.end(body) }
const owner = (id, hidden = false) => `<section id="${id}" class="fi-fo-field-wrp" ${hidden ? 'hidden' : ''}><span hidden data-fff-asset-batch data-fff-asset-consumer="probe" data-fff-stylesheets='["/probe.css"]' data-fff-chunks="[]" data-fff-factory-owners='{"/probe.js":{"package":"example/startup","component":"probe","export":"default","abi":1}}'></span><div x-fff-load x-fff-load-src="/probe.js" x-data="startupProbe('${id}')"><button data-fff-open-trigger @click="window.opened.push(id)">Open</button><input data-fff-edit-intent @beforeinput.prevent="window.edited.push([id, $event.data])"></div></section>`
const server = createServer((request, response) => {
    const url = new URL(request.url, 'http://localhost')
    const name = url.pathname
    if (name === '/blank') return respond(response, 'text/html', '<!doctype html><html><head><meta charset="utf-8"></head><body x-data><script src="/alpine.js"></script></body></html>')
    if (name === '/gate.js') return respond(response, 'text/javascript', gateModule.outputFiles[0].contents)
    if (name === '/') {
        const caseId = url.searchParams.get('case')
        const runtime = `${runtimePath}?case=${caseId}`
        return respond(response, 'text/html', `<!doctype html><html><head><meta charset="utf-8"></head><body x-data><script>window.opened=[];window.edited=[];window.initialized=[];window.destroyed=[];window.authorEffects=[]</script>${['first', 'double', 'escape', 'edit', 'removed'].map(id => owner(id)).join('')}${owner('dormant', true)}<script data-fff-runtime-src="${runtime}" src="/dist/core/flex-fff-load-bootstrap.js"></script><script data-fff-runtime-src="${runtime}" src="/dist/core/flex-fff-load-bootstrap.js"></script><script data-fff-runtime-src="${runtime}" src="/dist/core/flex-field-asset-injector.js"></script><script src="/alpine.js"></script></body></html>`)
    }
    if (name === '/alpine.js') return respond(response, 'text/javascript', alpine.outputFiles[0].contents)
    if (name === '/probe.css') return respond(response, 'text/css', '.fi-fo-field-wrp{padding:4px}')
    if (name === '/probe.js') {
        requests.push(name)
        return respond(response, 'text/javascript', author.outputFiles[0].contents)
    }
    if (name === runtimePath) {
        const caseId = url.searchParams.get('case')
        requests.push(`${name}?case=${caseId}`)
        const send = () => respond(response, 'text/javascript', fs.readFileSync(path.join(root, 'resources', name)))
        if (released.has(caseId)) send()
        else waiting.set(caseId, send)
        return
    }
    if (name.startsWith('/dist/components/') && allowed.has(path.basename(name))) {
        requests.push(name)
        return respond(response, 'text/javascript', fs.readFileSync(path.join(root, 'resources', name)))
    }
    if (['/dist/core/flex-fff-load-bootstrap.js', '/dist/core/flex-field-asset-injector.js'].includes(name)) {
        return respond(response, 'text/javascript', fs.readFileSync(path.join(root, 'resources', name)))
    }
    response.writeHead(404); response.end()
})
let browser
try {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve))
    browser = await launchCoreBrowser({ headless: true })
    const page = await browser.newPage()
    page.on('pageerror', error => errors.push(error.message))
    await page.goto(`http://127.0.0.1:${server.address().port}/?case=handoff`, { waitUntil: 'domcontentloaded' })
    await page.waitForFunction(() => window.alpineStarted)
    assert.equal(requests.filter(url => url.includes('case=handoff')).length, 1, 'Duplicate tags made competing native imports')
    assert.deepEqual(await page.evaluate(() => window.initialized), [])
    assert.equal(await page.locator('#first [x-fff-load]').getAttribute('x-ignore'), '')
    await page.evaluate(() => {
        const click = id => document.querySelector(`#${id} button`).click()
        click('first'); click('double'); click('double'); click('escape')
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
        click('first')
        const input = document.querySelector('#edit input')
        input.dispatchEvent(new InputEvent('beforeinput', { data: '7', inputType: 'insertText', bubbles: true, cancelable: true }))
        click('removed'); document.getElementById('removed').remove()
    })
    assert.equal(await page.locator('#first button').getAttribute('aria-busy'), 'true')
    released.add('handoff'); waiting.get('handoff')(); waiting.delete('handoff')
    await page.waitForFunction(() => window.initialized.length === 4 && window.opened.length === 1 && window.edited.length === 1, null, { timeout: 15_000 }).catch(async error => {
        console.error(await page.evaluate(() => ({ initialized: window.initialized, opened: window.opened, edited: window.edited,
            owners: [...document.querySelectorAll('[x-fff-load]')].map(el => ({ id: el.parentElement.id, async: el._x_async, ignored: el._x_ignore, ready: el._fffFactoryReady })),
            diagnostics: window.FffAssetInjector?.getDiagnostics?.() })), errors, requests)
        throw error
    })
    assert.deepEqual(await page.evaluate(() => window.opened), ['first'])
    assert.deepEqual(await page.evaluate(() => window.edited), [['edit', '7']])
    assert.deepEqual((await page.evaluate(() => window.initialized)).sort(), ['double', 'edit', 'escape', 'first'])
    assert.equal(await page.locator('#first button').getAttribute('aria-busy'), null)
    assert.deepEqual(errors, [])
    proofs.push('pre-Alpine ignore; one native runtime flight; first click/edit replay; double/Escape cancellation; detached owner never initializes')
    await page.evaluate(() => { document.getElementById('dormant').hidden = false })
    await page.waitForFunction(() => window.initialized.includes('dormant'), null, { timeout: 5000 }).catch(async error => {
        console.error(await page.evaluate(() => ({ initialized: window.initialized,
            dormant: document.getElementById('dormant').outerHTML,
            async: document.querySelector('#dormant [x-fff-load]')._x_async,
            active: window.FffAssetInjector?.isElementActive?.(document.querySelector('#dormant [x-fff-load]')),
            diagnostics: window.FffAssetInjector?.getDiagnostics?.() })), errors)
        throw error
    })
    assert.equal(requests.filter(url => url === '/probe.js').length, 1)
    await page.evaluate(() => {
        window.removedAuthor = document.querySelector('#first [x-fff-load]')
        window.removedAuthor.dispatchEvent(new Event('author-effect'))
        document.getElementById('first').remove()
    })
    await page.waitForFunction(() => window.destroyed.includes('first'))
    assert.equal((await page.evaluate(() => window.destroyed)).filter(id => id === 'first').length, 1)
    await page.evaluate(() => window.removedAuthor.dispatchEvent(new Event('author-effect')))
    assert.deepEqual(await page.evaluate(() => window.authorEffects), ['first'], 'Author listener survived owner destruction')
    proofs.push('dormant factory activation and actual Alpine removal cleanup occur exactly once')
    proofs.push('compiled third-party SDK facade uses installed primitives; no private runtime inputs; owned event listener releases with actual Alpine destroy')
    await page.evaluate(html => {
        const body = document.createElement('body'); body.setAttribute('x-data', ''); body.innerHTML = html
        document.body.replaceWith(body)
        document.dispatchEvent(new Event('livewire:navigated'))
    }, owner('replacement'))
    await page.waitForFunction(() => window.initialized.includes('replacement'))
    assert.equal((await page.evaluate(() => window.initialized)).filter(id => id === 'replacement').length, 1)
    assert.deepEqual(errors, [])
    proofs.push('actual document.body replacement and Livewire navigation reconnect the runtime; proxy initializes new owner exactly once')
    await page.goto(`http://127.0.0.1:${server.address().port}/blank`)
    const failures = await page.evaluate(async () => {
        const { installStartupGate } = await import('/gate.js')
        let resolve, imports = 0, installations = 0, initializations = 0
        const late = new Promise(done => { resolve = done })
        const module = { installCoreRuntime() { installations++ }, initializeEarlyOwner(_, el) { initializations++; el._x_async = 'loaded' } }
        const startup = installStartupGate({ document, moduleUrl: '/held-runtime.js', deadlineMs: 10,
            importModule() { imports++; return imports === 1 ? late : Promise.resolve(module) } })
        const el = document.createElement('div'); el.setAttribute('x-fff-load', ''); document.body.append(el)
        let errors = 0; el.addEventListener('fff-load-error', () => errors++)
        Alpine.initTree(el)
        await startup.start().catch(() => {})
        await Promise.resolve()
        const timedOut = errors === 1 && el._x_async === 'failed' && installations === 0 && imports === 1
        resolve(module); await Promise.resolve(); await Promise.resolve()
        const lateIgnored = installations === 0 && initializations === 0
        el.dispatchEvent(new CustomEvent('fff-load-retry'))
        await startup.start(); await Promise.resolve()
        const retried = installations === 1 && initializations === 1 && imports === 2
        const clone = el.cloneNode(true)
        Alpine.mutateDom(() => document.body.append(clone))
        Alpine.cloneNode(el, clone)
        await Promise.resolve(); await Promise.resolve()
        const cloneSuppressed = initializations === 1
        let conflict = false
        try { installStartupGate({ document, moduleUrl: '/different-release.js' }) } catch { conflict = true }
        Alpine.destroyTree(el); el.remove(); Alpine.destroyTree(clone); clone.remove()
        return { timedOut, lateIgnored, retried, conflict, cloneSuppressed }
    })
    assert.deepEqual(failures, { timedOut: true, lateIgnored: true, retried: true, conflict: true, cloneSuppressed: true })
    assert.deepEqual(errors, [])
    proofs.push('bounded startup deadline; late completion cannot install or init; explicit retry owns one flight; conflicting release rejected; Alpine clone suppressed before async continuation')
    console.log(JSON.stringify({ browser: await browser.version(), actualDistribution: true, installedAlpine: true, bootstrapBytes: fs.statSync(path.join(root, 'resources/dist/core/flex-fff-load-bootstrap.js')).size, proofs, errors }))
} finally {
    await browser?.close(); server.closeAllConnections()
    await new Promise(resolve => server.close(resolve))
}
