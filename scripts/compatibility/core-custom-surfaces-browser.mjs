import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import { promisify } from 'node:util'
import { execFile } from 'node:child_process'
import { createServer } from 'node:http'
import { build } from 'esbuild'
import { launchCoreBrowser } from './browser-profile.mjs'
import { createFlexSdkPlugin } from '../assets/flex-sdk-plugin.mjs'

const root = path.resolve(import.meta.dirname, '../..')
const native = await promisify(execFile)('php', ['scripts/compatibility/core-render-context.php', '--browser-fixtures'], { cwd: root, maxBuffer: 4 * 1024 * 1024 })
const fixture = JSON.parse(Buffer.from(native.stdout.split('\n').find(line => line.startsWith('BROWSER_FIXTURES:')).slice('BROWSER_FIXTURES:'.length), 'base64').toString())
const alpine = await build({ stdin: { contents: `import { Alpine } from ${JSON.stringify(fixture.alpineModule)}; globalThis.Alpine=Alpine; Alpine.start()`, resolveDir: root }, bundle: true, format: 'iife', minify: true, write: false, logLevel: 'silent' })
const authorOptions = { absWorkingDir: root, bundle: true, format: 'esm', minify: true, write: false, metafile: true, plugins: [createFlexSdkPlugin()], logLevel: 'silent' }
const host = await build({ ...authorOptions, stdin: { resolveDir: root, contents: `
import { createAssetSurface } from '@bjanczak/flex-fields/sdk/v1';
window.createControl = (id, parent=null, signal=null) => createAssetSurface(document.getElementById(id), {parent,signal});
window.openControl = (id, handle) => handle.activate().then(ready => {
    if (ready && handle.ready) document.getElementById(id).hidden=false;
    window.results[id]={ready,state:handle.state}; return ready;
});` } })
const field = await build({ ...authorOptions, stdin: { resolveDir: root, contents: `
import { createResourceScope } from '@bjanczak/flex-fields/sdk/v1';
export default id => ({ scope:null,
    async init() { this.scope=createResourceScope(); window.started.push(id);
        await new Promise(resolve => window.finishFactory[id]=resolve);
        if (!this.scope.disposed) window.initialized.push(id);
    }, destroy() { this.scope?.dispose(); window.destroyed.push(id); }
});` } })
assert.ok(Object.keys(host.metafile.inputs).every(name => !name.includes('resources/js/')))
assert.ok(Object.keys(field.metafile.inputs).every(name => !name.includes('resources/js/')))
const dist = path.join(root, 'resources/dist/components')
const manifest = JSON.parse(fs.readFileSync(path.join(dist, 'alpine-manifest.json')))
const allowed = new Set([...manifest.__entries__.map(name => `${name}.js`), ...manifest.__shared_chunks__])
const batch = (styles, factory = false) => `<span hidden data-fff-asset-batch data-fff-asset-consumer="probe" data-fff-stylesheets='${JSON.stringify(styles)}' data-fff-chunks="[]" ${factory ? `data-fff-factory-owners='{"/author-field.js":{"package":"example/surfaces","component":"probe","export":"default","abi":1}}'` : ''}></span>`
const surface = (id, style) => `<div id="${id}" hidden data-fff-asset-active="false"><section class="acme-field" data-fff-component="probe">${batch(['/shared.css', style], true)}<div x-fff-load x-fff-load-src="/author-field.js" x-data="surfaceField('${id}')">${id}</div></section></div>`
const requests = [], errors = [], proofs = [], waiting = []; let released = false, faultCount = 0
const send = (response, type, data) => { response.writeHead(200, { 'Content-Type': type, 'Cache-Control': 'no-store' }); response.end(data) }
const server = createServer((request, response) => {
    const url = new URL(request.url, 'http://localhost'), name = url.pathname
    if (name === '/') return send(response, 'text/html', `<!doctype html><html><head><meta charset="utf-8"></head><body x-data>
        <script>window.started=[];window.initialized=[];window.destroyed=[];window.finishFactory={};window.results={};window.controls={}</script>
        <section id="page">${batch(['/shared.css'])}Page</section>
        ${surface('parent', '/dialog.css')}${surface('child', '/child.css')}${surface('race', '/race.css')}${surface('fault', '/fault.css')}${surface('abort', '/abort.css')}
        <script data-fff-runtime-src="/dist/flex-core-runtime.js" src="/core/flex-fff-load-bootstrap.js"></script>
        <script data-fff-runtime-src="/dist/flex-core-runtime.js" src="/core/flex-field-asset-injector.js"></script><script src="/alpine.js"></script></body></html>`)
    if (name === '/alpine.js') return send(response, 'text/javascript', alpine.outputFiles[0].contents)
    if (name === '/author-host.js') return send(response, 'text/javascript', host.outputFiles[0].contents)
    if (name === '/author-field.js') { requests.push(name); return send(response, 'text/javascript', field.outputFiles[0].contents) }
    if (name.startsWith('/dist/') && allowed.has(path.basename(name))) { requests.push(name); return send(response, 'text/javascript', fs.readFileSync(path.join(dist, path.basename(name)))) }
    if (['/core/flex-fff-load-bootstrap.js', '/core/flex-field-asset-injector.js'].includes(name)) return send(response, 'text/javascript', fs.readFileSync(path.join(root, 'resources/dist/core', path.basename(name))))
    if (name.endsWith('.css')) {
        requests.push(name)
        const complete = () => send(response, 'text/css', `#${path.basename(name, '.css')} { color: rgb(0,128,0) }`)
        if (name === '/race.css' && !released) waiting.push(complete)
        else if (name === '/fault.css' && ++faultCount === 1) { response.writeHead(503); response.end() }
        else complete()
        return
    }
    response.writeHead(404); response.end()
})
let browser
try {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve))
    browser = await launchCoreBrowser({ headless: true })
    const page = await browser.newPage(); page.setDefaultTimeout(15000)
    page.on('pageerror', error => errors.push(error.message))
    const origin = `http://127.0.0.1:${server.address().port}`
    await page.goto(origin)
    await page.waitForFunction(() => window.FffAssetInjector && document.querySelector('#page [data-fff-assets-ready]'))
    await page.evaluate(async () => { await import('/author-host.js'); window.controls.parent=await window.createControl('parent'); window.controls.child=await window.createControl('child', window.controls.parent) })
    assert.equal(requests.filter(name => name === '/author-field.js').length, 0)
    assert.equal(requests.filter(name => name === '/dialog.css' || name === '/child.css').length, 0)
    proofs.push('closed custom dialogs import no optional field module or CSS; author bundles use only the public externalized SDK')

    await page.evaluate(() => { window.openControl('parent', window.controls.parent) })
    await page.waitForFunction(() => window.started.includes('parent'))
    assert.deepEqual(await page.evaluate(() => ({ hidden: document.getElementById('parent').hidden, ready: window.controls.parent.ready, done: !!window.results.parent })), { hidden: true, ready: false, done: false })
    await page.evaluate(() => window.finishFactory.parent())
    await page.waitForFunction(() => window.results.parent?.ready)
    assert.equal(await page.evaluate(() => document.getElementById('parent').hidden), false)
    proofs.push('hidden host remains unrevealed through real CSS/module preparation and async Alpine init; activation acknowledges only complete readiness')

    await page.evaluate(() => { window.controls.parent.suspend(); document.getElementById('parent').hidden=true; window.openControl('child',window.controls.child) })
    await page.waitForFunction(() => window.started.includes('child'))
    await page.evaluate(() => window.finishFactory.child()); await page.waitForFunction(() => window.results.child?.ready)
    const refs = () => page.evaluate(() => window.FffAssetInjector.getConsumerGraph().getResourceRefCount({ kind:'stylesheet', url:'/shared.css' }))
    assert.equal(await refs(), 3)
    await page.evaluate(() => { window.controls.child.close(); document.getElementById('child').hidden=true })
    assert.equal(await refs(), 2)
    assert.equal(await page.evaluate(() => window.controls.parent.state), 'suspended')
    assert.equal(await page.locator('link[href$="/dialog.css"]').count(), 1)
    proofs.push('teleported child uses an independent owner; child close preserves a suspended parent and shared page stylesheet references')

    await page.evaluate(() => { window.controls.parent.close(); document.getElementById('parent').hidden=true })
    assert.equal(await refs(), 1); assert.equal(await page.locator('link[href$="/dialog.css"]').count(), 0)
    assert.equal(await page.locator('link[href$="/shared.css"]').count(), 1)
    assert.equal(await page.evaluate(() => window.controls.child.activate()), false)
    proofs.push('closing a parent releases only its family, invalidates child activation and retains underlying page CSS')

    await page.evaluate(async () => { window.controls.race=await window.createControl('race'); window.openControl('race',window.controls.race) })
    await page.waitForFunction(() => window.FffAssetInjector.getInflightUrls().some(url => url.endsWith('/race.css')))
    await page.evaluate(() => window.controls.race.close())
    await page.waitForFunction(() => window.results.race?.ready === false)
    assert.equal(await page.evaluate(() => window.started.includes('race')), false)
    released=true; for (const complete of waiting.splice(0)) complete()
    await page.waitForFunction(() => !window.FffAssetInjector.getInflightUrls().some(url => url.endsWith('/race.css')))
    assert.equal(await page.evaluate(() => document.getElementById('race').hidden), true)
    await page.evaluate(() => { delete window.results.race; window.openControl('race',window.controls.race) })
    await page.waitForFunction(() => window.started.includes('race')); await page.evaluate(() => window.finishFactory.race())
    await page.waitForFunction(() => window.results.race?.ready)
    proofs.push('closing during slow shared IO settles immediately without revealing; a fresh open reuses evaluated modules and finishes normally')

    await page.evaluate(async () => { window.controls.fault=await window.createControl('fault'); try { await window.controls.fault.activate() } catch { window.faultRejected=true } })
    assert.equal(await page.evaluate(() => window.faultRejected && !window.controls.fault.ready), true)
    await page.evaluate(() => { window.openControl('fault',window.controls.fault) })
    await page.waitForFunction(() => window.started.includes('fault')); await page.evaluate(() => window.finishFactory.fault())
    await page.waitForFunction(() => window.results.fault?.ready)
    proofs.push('a real HTTP503 remains an observable readiness failure; retry recovers without revealing stale or unstyled content')

    await page.evaluate(async () => { window.abortOwner=new AbortController(); window.controls.abort=await window.createControl('abort',null,window.abortOwner.signal); window.openControl('abort',window.controls.abort) })
    await page.waitForFunction(() => window.started.includes('abort'))
    await page.evaluate(() => window.abortOwner.abort()); await page.waitForFunction(() => window.results.abort?.ready === false)
    await page.evaluate(() => window.finishFactory.abort())
    assert.equal(await page.evaluate(() => window.controls.abort.state), 'disposed')
    assert.equal(await page.evaluate(() => document.getElementById('abort').hidden), true)
    proofs.push('owner abort during async init disposes the handle and cannot reveal after late factory completion')

    await page.evaluate(() => { const body=document.createElement('body'); body.innerHTML='<div id="new-root" hidden data-fff-asset-active="false">Fresh document body</div>'; document.body.replaceWith(body); document.dispatchEvent(new CustomEvent('livewire:navigated')) })
    await page.waitForFunction(() => window.FffAssetInjector.getCustomSurfaceSnapshot().surfaces === 0)
    assert.equal(await page.evaluate(() => window.controls.race.state), 'disposed')
    assert.equal(await page.evaluate(async () => { const h=await window.createControl('new-root'); const ready=await h.activate(); h.dispose(); return ready }), true)
    assert.equal(requests.filter(name => name === '/author-field.js').length, 1)
    assert.deepEqual(errors, [])
    proofs.push('body replacement prunes old handles and permits a fresh surface; one author factory module was fetched across all instances')
    console.log(JSON.stringify({ browser: await browser.version(), groups: proofs.length, proofs, realAlpine: true, compiledDistribution: true, errors, testsModified: false }))
} catch (error) {
    console.error(JSON.stringify({ proofs, requests, errors })); throw error
} finally {
    await browser?.close(); server.closeAllConnections()
    await new Promise(resolve => server.close(resolve))
}
