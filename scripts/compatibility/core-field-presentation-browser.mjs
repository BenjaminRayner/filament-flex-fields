import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import { promisify } from 'node:util'
import { execFile } from 'node:child_process'
import { createServer } from 'node:http'
import { build } from 'esbuild'
import { launchCoreBrowser } from './browser-profile.mjs'

// This covers conditional native domain bridges and declared loader ownership,
// which the existing synchronous first-paint browser probe does not exercise.
const root=path.resolve(import.meta.dirname,'../..'), dist=path.join(root,'resources/dist/components')
const rendered=await promisify(execFile)('php',['scripts/compatibility/core-render-context.php','--browser-fixtures'],{cwd:root,maxBuffer:4*1024*1024})
const line=rendered.stdout.split('\n').find(line=>line.startsWith('BROWSER_FIXTURES:'))
assert.ok(line,'Installed Composer host did not supply its Alpine module.')
const native=JSON.parse(Buffer.from(line.slice('BROWSER_FIXTURES:'.length),'base64').toString())
assert.ok(path.isAbsolute(native.alpineModule)&&fs.existsSync(native.alpineModule))
const alpine=await build({stdin:{resolveDir:root,contents:`import { Alpine } from ${JSON.stringify(native.alpineModule)};window.Alpine=Alpine;`},bundle:true,format:'iife',minify:true,write:false,logLevel:'silent'})
const first=JSON.parse((await promisify(execFile)('php',['scripts/compatibility/core-first-paint-rendering.php','--json'],{cwd:root})).stdout)
const manifest=JSON.parse(fs.readFileSync(path.join(dist,'alpine-manifest.json')))
const allowed=new Set([...manifest.__entries__.map(name=>name+'.js'),...manifest.__shared_chunks__])
const snapshot=JSON.parse(fs.readFileSync(path.join(root,'build/asset-analysis/components.json')))
const bridges=Object.keys(snapshot.outputs).filter(file=>Object.keys(snapshot.outputs[file].inputs).some(input=>input.startsWith('resources/js/runtime/filament/presentation/'))).map(file=>path.basename(file))
assert.equal(bridges.length,2,'Domain bridges did not retain independent dynamic boundaries.')
const owners=Object.fromEntries(['segment','timezone'].map(name=>[name,manifest.__shared_chunks__.find(file=>file.startsWith('flex-fields-first-paint-'+name+'-'))]))
const batch=id=>`<span hidden data-fff-asset-batch data-fff-asset-consumer="${id}" data-fff-stylesheets="[]" data-fff-chunks="[]"></span>`
const segment=script=>`<div id="segment-field">${batch('segment-control')}<div id="scroll" data-fff-segment-overflow class="scroll"><div class="track">${Array.from({length:8},(_,i)=>`<button data-segment-value="${i}" data-segment-selected="${i===4}">${i}</button>`).join('')}</div>${script?`<script>${script}</script>`:''}</div></div>`
const timezone=script=>`<div class="fff-timezone-field" id="timezone-field">${batch('timezone-field')}<span class="fff-timezone-field__ssr-label is-placeholder">Placeholder</span><span class="fff-timezone-field__ssr-meta"></span><div hidden data-fff-timezone-boot data-fff-timezone-catalog='{"Europe/Warsaw":["Warsaw","UTC+02:00"]}'></div>${script?`<script>${script}</script>`:''}</div>`
const requests=[],errors=[],proofs=[],waiting=[];let delaySegment=false
const send=(response,type,bytes)=>{response.writeHead(200,{'Content-Type':type,'Cache-Control':'no-store'});response.end(bytes)}
const server=createServer((request,response)=>{
    const route=new URL(request.url,'http://localhost').pathname
    if(['/cold','/race','/warm','/orphan'].includes(route)) {
        const warm=route==='/warm', race=route==='/race'
        const fields=segment(warm?first.segment[0]:'')+(race?'':timezone(warm?first.timezone[0]:''))
        const orphan=route==='/orphan'?`<section class="fi-fo-field-wrp"><div id="orphan" x-fff-load x-fff-load-src="/components/select-field.js" x-data="missingFactory()"></div><section>${batch('unrelated-child')}</section></section>`:''
        const active=warm?'true':'false'
        return send(response,'text/html',`<!doctype html><html><head><meta charset="utf-8"><style>.scroll{width:240px;overflow:auto}.track{display:flex;width:max-content}.track button{width:100px;flex:none;height:32px}</style></head><body>${batch('plain')}<section id="surface" data-fff-asset-active="${active}" style="visibility:${warm?'visible':'hidden'}">${fields}</section>${orphan}</body></html>`)
    }
    if(route==='/alpine.js')return send(response,'text/javascript',alpine.outputFiles[0].contents)
    const name=route.slice(1)
    if(allowed.has(name)) {
        requests.push(name)
        const deliver=()=>send(response,'text/javascript',fs.readFileSync(path.join(dist,name)))
        if(delaySegment&&name===owners.segment){waiting.push(deliver);return}
        return deliver()
    }
    if(route!=='/favicon.ico')errors.push('Unexpected request: '+route)
    response.writeHead(404);response.end()
})
let browser
try {
    await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));browser=await launchCoreBrowser({headless:true})
    const context=await browser.newContext({timezoneId:'Europe/Warsaw'}), origin=`http://127.0.0.1:${server.address().port}`
    const page=await context.newPage();page.on('pageerror',error=>errors.push(error.message));page.setDefaultTimeout(15000)
    const boot=async()=>page.evaluate(async()=>{
        const module=await import('/flex-core-runtime.js');window.runtime=module.installCoreRuntime();await window.runtime.ensureAssets(document)
    })
    await page.goto(origin+'/cold');await boot()
    assert.ok(bridges.every(name=>!requests.includes(name)));assert.ok(Object.values(owners).every(name=>!requests.includes(name)))
    assert.equal(await page.evaluate(()=>globalThis[Symbol.for('fff.first-paint.segment.v1')]!==undefined),false)
    proofs.push('actual compiled runtime on a plain page with closed domain fields requests neither presentation bridge nor synchronous owner')

    await page.evaluate(async()=>{window.handle=await globalThis[Symbol.for('fff.sdk.v1')].createAssetSurface(document.getElementById('surface'));window.opened=await handle.activate()})
    const prepared=await page.evaluate(()=>({ready:window.opened,positioned:document.getElementById('scroll').dataset.ssrScrollPositioned,left:document.getElementById('scroll').scrollLeft,label:document.querySelector('.fff-timezone-field__ssr-label').textContent,frozen:Object.isFrozen(globalThis[Symbol.for('fff.first-paint.segment.v1')])}))
    assert.equal(prepared.ready,true);assert.equal(prepared.positioned,'true');assert.ok(prepared.left>0);assert.equal(prepared.label,'Warsaw');assert.ok(prepared.frozen)
    for(const name of [...bridges,...Object.values(owners)])assert.equal(requests.filter(value=>value===name).length,1)
    proofs.push('first custom activation loads each required bridge/owner once and returns ready after segment geometry and the hidden timezone metadata are painted')
    await page.evaluate(async()=>{handle.close();await handle.activate();const old=document.querySelector('[data-segment-selected="true"]');const incoming=old.cloneNode(true);incoming.setAttribute('data-segment-selected','false');runtime.handleMorphUpdating({el:old,toEl:incoming});window.morphSelected=incoming.getAttribute('data-segment-selected')})
    assert.equal(await page.evaluate(()=>window.morphSelected),'true')
    for(const name of [...bridges,...Object.values(owners)])assert.equal(requests.filter(value=>value===name).length,1)
    proofs.push('reopen and native synchronous morph preserve mounted presentation without importing a second implementation')

    requests.length=0;delaySegment=true;await page.goto(origin+'/race');await boot()
    await page.evaluate(async()=>{window.handle=await globalThis[Symbol.for('fff.sdk.v1')].createAssetSurface(document.getElementById('surface'));window.settled=false;window.operation=handle.activate().then(ready=>{window.ready=ready;window.settled=true})})
    await page.waitForFunction(()=>window.handle.state==='active');await new Promise((resolve,reject)=>{const deadline=Date.now()+12000;const inspect=()=>waiting.length?resolve():Date.now()>deadline?reject(Error('Domain owner HTTP was never requested.')):setTimeout(inspect,5);inspect()})
    await page.evaluate(()=>handle.close());await page.waitForFunction(()=>window.settled===true)
    assert.equal(await page.evaluate(()=>window.ready),false)
    delaySegment=false;for(const deliver of waiting.splice(0))deliver()
    await page.evaluate(async()=>{await window.operation;await runtime.ensureAssets(document)})
    assert.equal(await page.evaluate(()=>document.getElementById('scroll').dataset.ssrScrollPositioned),undefined)
    await page.evaluate(async()=>{window.ready=await handle.activate()});assert.equal(await page.evaluate(()=>window.ready),true)
    assert.equal(await page.evaluate(()=>document.getElementById('scroll').dataset.ssrScrollPositioned),'true')
    assert.equal(requests.filter(name=>name===owners.segment).length,1)
    proofs.push('closing during delayed native owner HTTP settles immediately with no stale paint; reopening reuses the shared completed import')

    requests.length=0;await page.goto(origin+'/warm');await boot()
    assert.ok(Object.values(owners).every(name=>!requests.includes(name)))
    const warm=await page.evaluate(()=>{const old=document.querySelector('[data-segment-selected="true"]'),next=old.cloneNode(true);next.setAttribute('data-segment-selected','false');runtime.handleMorphUpdating({el:old,toEl:next});return {selected:next.getAttribute('data-segment-selected'),label:document.querySelector('.fff-timezone-field__ssr-label').textContent}})
    assert.deepEqual(warm,{selected:'true',label:'Warsaw'})
    proofs.push('native SSR-installed owner works before any lazy presentation bridge and survives morph with no duplicate owner request')

    const partial=await page.evaluate(()=>{
        let intercepted
        window.Livewire={hook(){return ()=>{}},interceptMessage(callback){intercepted=callback;return ()=>{}}}
        document.dispatchEvent(new CustomEvent('livewire:init'))
        const owner=document.createElement('div');owner.setAttribute('wire:id','partial-owner')
        const live=document.getElementById('scroll').cloneNode(true);live.setAttribute('wire:partial','retained');live.setAttribute('wire:key','partial-shell')
        const selected=live.querySelector('[data-segment-selected="true"]');selected.setAttribute('wire:key','selected')
        owner.append(live);document.body.append(owner)
        const incoming=live.cloneNode(true);delete incoming.dataset.ssrScrollPositioned;incoming.querySelector('[wire\\:key="selected"]').setAttribute('data-segment-selected','false')
        const payload={effects:{partials:{retained:incoming.outerHTML}}}
        intercepted({message:{component:{el:owner}},onSuccess(callback){callback({payload})}})
        const fragment=document.createElement('div');fragment.innerHTML=payload.effects.partials.retained
        return {positioned:fragment.firstElementChild.dataset.ssrScrollPositioned,selected:fragment.querySelector('[wire\\:key="selected"]').getAttribute('data-segment-selected')}
    })
    assert.deepEqual(partial,{positioned:'true',selected:'true'})
    assert.ok(Object.values(owners).every(name=>!requests.includes(name)))
    proofs.push('actual compiled native response adapter preserves retained segment geometry/selection in a controlled Filament partial without eager domain imports')

    requests.length=0;await page.goto(origin+'/orphan');await boot()
    await page.addScriptTag({url:origin+'/alpine.js'});await page.evaluate(()=>Alpine.start())
    await page.waitForFunction(()=>document.getElementById('orphan')._x_async==='failed')
    assert.ok(!requests.some(name=>name==='select-field.js'));assert(!errors.some(error=>error.includes('missingFactory')))
    proofs.push('installed native Alpine refuses orphan factory with only historical wrapper/filename and a nested unrelated plan before requesting or evaluating JS')
    assert.deepEqual(errors,[])
    console.log(JSON.stringify({browser:browser.version(),groups:proofs.length,actualCompiledEntries:true,proofs,errors},null,2))
} finally {
    delaySegment=false;for(const deliver of waiting.splice(0))deliver();await browser?.close();await new Promise(resolve=>server.close(resolve))
}
