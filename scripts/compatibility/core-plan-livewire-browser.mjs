import assert from 'node:assert/strict'
import { execFileSync } from 'node:child_process'
import { createServer } from 'node:http'
import path from 'node:path'
import { build } from 'esbuild'
import { launchCoreBrowser } from './browser-profile.mjs'

const root = path.resolve(import.meta.dirname, '../..')
const fixture = JSON.parse(execFileSync('php', ['scripts/compatibility/core-plan-livewire.php'], { cwd: root, encoding: 'utf8', maxBuffer: 4 * 1024 * 1024 }))
const source = `
import { prepareLivewirePlanEffects } from './resources/js/runtime/filament/response-plans.js'
import { prepareFilamentPartials } from './resources/js/runtime/filament/partial-assets.js'
import { readReferencedPlan } from './resources/js/runtime/assets/plan-dictionary.js'
const assert = (value, message) => { if (!value) throw Error(message) }
const reject = callback => { try { callback() } catch { return }; throw Error('Conflicting definition was accepted') }
const parse = html => { const root = document.createElement('div'); root.innerHTML = html; return root }
window.probeResult = (async () => {
    const fixture = await (await fetch('/fixture')).json(), proofs = []
    const first = document.querySelector('[data-fff-asset-batch]'), oldPlan = readReferencedPlan(first)
    const before = document.body.innerHTML
    const payload = { effects: { html: fixture.full, islandFragments: [fixture.island] } }
    prepareLivewirePlanEffects(payload, document)
    assert(document.body.innerHTML === before && payload.effects.html === fixture.full, 'Response admission mutated live DOM or application HTML')
    // A warm catalog is enough even if native morph temporarily removes the old
    // template before inserting the incoming one. Each mounted owner stays independent.
    document.querySelector('[data-fff-plan-definition]').remove()
    const full = parse(payload.effects.html); full.querySelector('[data-fff-plan-definition]').remove()
    document.body.append(full)
    for (const batch of full.querySelectorAll('[data-fff-asset-batch]')) assert(readReferencedPlan(batch) === oldPlan, 'Native full response was not admitted before morph')
    assert(readReferencedPlan(first) === oldPlan, 'Removing an old template invalidated a retained owner')
    proofs.push('full response admission precedes native DOM replacement and preserves existing owner projections')
    const island = parse(fixture.island), comments = [...island.childNodes].filter(node => node.nodeType === Node.COMMENT_NODE)
    assert(comments.length === 2 && island.querySelector('br'), 'Native island boundaries changed')
    island.querySelector('[data-fff-plan-definition]').remove(); document.body.append(island)
    for (const batch of island.querySelectorAll('[data-fff-asset-batch]')) assert(readReferencedPlan(batch) === oldPlan, 'Island plan was not admitted before sibling morph')
    proofs.push('island sibling fragments retain native comments and find prepared descriptors before insertion')

    const owner = document.createElement('div'); owner.setAttribute('wire:id', 'partial-owner')
    owner.innerHTML = '<section wire:partial="first"></section><section wire:partial="second"></section>'
    const child = document.createElement('div'); child.setAttribute('wire:id', 'nested-child'); child.innerHTML = '<section wire:partial="first"></section>'
    owner.append(child); document.body.append(owner)
    const partialPayload = { effects: { partials: { ...fixture.partials } } }, prepared = []
    prepareFilamentPartials({ message: { component: { el: owner } }, payload: partialPayload, document,
        surfaces: {}, primePreparedStyles(incoming) { prepared.push(incoming); for (const batch of incoming.querySelectorAll('[data-fff-asset-batch]')) assert(readReferencedPlan(batch) === oldPlan, 'Native partial primed before dictionary admission') } })
    assert(prepared.length === 2, 'Nested child partial names prevented independent parent preparation')
    assert(partialPayload.effects.partials.first.includes('wire:partial="first"') && child.querySelectorAll('[data-fff-asset-batch]').length === 0, 'Partial admission changed native ownership')
    proofs.push('native Filament partial preparation admits both dictionaries before CSS preparation and excludes nested child targets')

    const conflict = parse(fixture.full), definition = conflict.querySelector('[data-fff-plan-definition]')
    const value = JSON.parse(definition.content.textContent); value.stylesheets = ['/conflict.css']
    definition.content.textContent = JSON.stringify(value)
    reject(() => prepareLivewirePlanEffects({ effects: { html: conflict.innerHTML } }, document))
    assert(readReferencedPlan(first) === oldPlan, 'Rejected conflict invalidated existing owner')
    proofs.push('a warm-cache same-ID conflict is rejected before native morph and leaves active projections intact')
    const plain = { effects: { html: '<div class="native">native</div>', partials: { unrelated: '<div>unchanged</div>' } } }
    const plainBefore = JSON.stringify(plain); prepareLivewirePlanEffects(plain, document)
    assert(JSON.stringify(plain) === plainBefore, 'Unrelated framework response was rewritten')
    proofs.push('unrelated HTML and native partial payloads remain unchanged')
    window.probeFinished = true; return proofs
})().catch(error => { window.probeFailure = error.message; throw error })
`
const output = await build({ stdin: { contents: source, resolveDir: root }, bundle: true, format: 'esm', minify: true, write: false, logLevel: 'silent' })
const requests = []
const server = createServer((request, response) => {
    requests.push(request.url)
    const send = (kind, body) => { response.writeHead(200, { 'Content-Type': kind }); response.end(body) }
    if (request.url === '/') return send('text/html', '<!doctype html><html><head><meta charset="utf-8"></head><body>' + fixture.mounted + '<script type="module" src="/probe.js"></script></body></html>')
    if (request.url === '/fixture') return send('application/json', JSON.stringify(fixture))
    if (request.url === '/probe.js') return send('text/javascript', output.outputFiles[0].text)
    response.writeHead(404); response.end()
})
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve))
const browser = await launchCoreBrowser({ headless: true })
try {
    const page = await browser.newPage(), errors = []
    page.on('pageerror', error => errors.push(error.message))
    await page.goto('http://127.0.0.1:' + server.address().port)
    await page.waitForFunction(() => window.probeFinished || window.probeFailure)
    const result = await page.evaluate(async () => ({ proofs: await window.probeResult, failure: window.probeFailure }))
    assert.equal(result.failure, undefined); assert.deepEqual(errors, [])
    assert.equal(requests.filter(url => /\.(css|wasm)$/.test(url)).length, 0, 'Dictionary admission acquired resources or activated owners')
    console.log(JSON.stringify({ browser: browser.version(), minifiedSourceBuild: true, frameworkProofs: fixture.proofs, browserProofs: result.proofs, resourceRequestsDuringAdmission: 0, errors }))
} finally {
    await browser.close(); await new Promise(resolve => server.close(resolve))
}
