import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import {createServer} from 'node:http'
import {launchCoreBrowser} from './browser-profile.mjs'

// Actual sealed owner/CSS; no application routes or source CSS substitutions.
const root=path.resolve(import.meta.dirname,'../..')
const directory=path.join(root,'resources/dist/components')
const manifest=JSON.parse(fs.readFileSync(path.join(directory,'alpine-manifest.json')))
const owner=manifest.__shared_chunks__.find(file=>file.startsWith('flex-fields-first-paint-readiness-'))
assert.ok(owner,'Missing synchronous readiness owner')
const css=fs.readFileSync(path.join(root,'resources/dist/css/core.css'),'utf8')
const code=fs.readFileSync(path.join(directory,owner),'utf8')
const errors=[],proofs=[]
const server=createServer((request,response)=>{
    if(request.url==='/core.css'){response.writeHead(200,{'Content-Type':'text/css'});response.end(css);return}
    response.writeHead(200,{'Content-Type':'text/html'})
    response.end(`<!doctype html><html><head><link rel="stylesheet" href="/core.css"><style>
        .owner{height:40px;width:200px;transition:opacity 5s}.child{visibility:visible;pointer-events:auto;transition:all 5s}
        </style>${request.url==='/unsupported'?'<script>window.CSSScopeRule=undefined</script>':''}
        ${request.url==='/fallback'?'':`<script>${code}</script>`}</head><body>
        <section class="owner" id="pending"><button class="child">Pending</button><span hidden data-fff-asset-batch></span></section>
        <section class="owner" id="ready"><button class="child">Ready</button><span hidden data-fff-asset-batch data-fff-assets-ready=""></span></section>
        <section class="owner" id="settling"><button class="child">Settling</button><span hidden data-fff-assets-settling data-fff-asset-batch data-fff-assets-ready=""></span></section>
        </body></html>`)
})
let browser
try{
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve))
 browser=await launchCoreBrowser({headless:true})
 const page=await browser.newPage()
 page.on('pageerror',e=>errors.push(e.message))
 for(const mode of ['scoped','fallback','unsupported']){
  await page.goto(`http://127.0.0.1:${server.address().port}/${mode}`)
  const result=await page.evaluate(()=>{
   const guard=[...document.styleSheets[0].cssRules].find(rule=>rule.conditionText?.includes('--fff-asset-paint-guard'))
   const fallback=[...guard.cssRules].find(rule=>rule.conditionText?.includes('--fff-asset-paint-fallback'))
   const sample=id=>{const element=document.getElementById(id),child=element.querySelector('button');const style=getComputedStyle(element),inner=getComputedStyle(child);return {opacity:style.opacity,pointer:inner.pointerEvents,visibility:inner.visibility,transition:inner.transitionDuration}}
   return {fallback:fallback.cssRules.length,pending:sample('pending'),ready:sample('ready'),settling:sample('settling')}
  })
  assert.equal(result.fallback===0,mode==='scoped',`${mode}: fallback selection`)
  assert.equal(result.pending.opacity,'0');assert.equal(result.pending.pointer,'none');assert.equal(result.pending.visibility,'hidden');assert.equal(result.pending.transition,'0s')
  assert.equal(result.ready.opacity,'1');assert.equal(result.ready.pointer,'auto');assert.equal(result.ready.visibility,'visible');assert.equal(result.ready.transition,'5s')
  assert.equal(result.settling.transition,'0s')
  proofs.push(`${mode}: synchronous pending seal, child overrides, ready neighbor and settling transitions`)
  if(mode==='scoped'){
   const dynamic=await page.evaluate(async()=>{
    const make=()=>{const root=document.createElement('section');root.className='owner';root.innerHTML='<button class="child">New pending</button><span hidden data-fff-asset-batch></span>';return root}
    const sample=root=>({marked:root.hasAttribute('data-fff-paint-owner'),opacity:getComputedStyle(root).opacity,pointer:getComputedStyle(root.firstElementChild).pointerEvents,visibility:getComputedStyle(root.firstElementChild).visibility})
    const first=make();document.body.append(first);await Promise.resolve();const inserted=sample(first)
    const body=document.createElement('body'),next=make();body.append(next);document.body.replaceWith(body);await Promise.resolve()
    return {inserted,replaced:sample(next)}
   })
   for(const sample of Object.values(dynamic)){assert.equal(sample.marked,true);assert.equal(sample.opacity,'0');assert.equal(sample.pointer,'none');assert.equal(sample.visibility,'hidden')}
   proofs.push('independent legacy roots are annotated before paint; document observer survives native body replacement')
   const leases=await page.evaluate(()=>{
    const api=globalThis[Symbol.for('fff.first-paint.readiness.v1')],args={document,window}
    const guard=[...document.styleSheets[0].cssRules].find(rule=>rule.conditionText?.includes('--fff-asset-paint-guard'))
    const group=[...guard.cssRules].find(rule=>rule.conditionText?.includes('--fff-asset-paint-fallback'))
    const first=api.enableScopedStyleReadiness(args),second=api.enableScopedStyleReadiness(args)
    first();first();const partial=group.cssRules.length;second();const restored=[...group.cssRules].map(rule=>rule.cssText)
    const again=api.enableScopedStyleReadiness(args),reacquired=group.cssRules.length;again()
    return {partial,restored,reacquired,final:[...group.cssRules].map(rule=>rule.cssText)}
   })
   assert.equal(leases.partial,0);assert.equal(leases.reacquired,0);assert.ok(leases.restored.length>0);assert.deepEqual(leases.final,leases.restored)
   const afterDisposal=await page.evaluate(()=>{
    const root=document.createElement('section');root.className='owner';root.innerHTML='<button class="child">Fallback pending</button><span hidden data-fff-asset-batch></span>';document.body.append(root)
    return {marked:root.hasAttribute('data-fff-paint-owner'),opacity:getComputedStyle(root).opacity,pointer:getComputedStyle(root.firstElementChild).pointerEvents,visibility:getComputedStyle(root.firstElementChild).visibility}
   })
   assert.equal(afterDisposal.marked,false);assert.equal(afterDisposal.opacity,'0');assert.equal(afterDisposal.pointer,'none');assert.equal(afterDisposal.visibility,'hidden')
   proofs.push('early lease handoff, independent retainers, idempotent release, exact restoration and reacquisition')
  }
 }
 assert.deepEqual(errors,[])
 for(const proof of proofs)console.log(`PASS: ${proof}`)
}finally{await browser?.close();await new Promise(resolve=>server.close(resolve))}
