import assert from 'node:assert/strict'
import fs from 'node:fs'
import { createFilamentFieldPresentation } from '../../resources/js/runtime/filament/field-presentation.js'
import { declaredComponentIdentity } from '../../resources/js/runtime/assets/component-scope.js'
import { createAssetRuntime } from '../../resources/js/runtime/asset-runtime-engine.js'
import { createFlexFieldAssetInjector } from '../../resources/js/runtime/asset-runtime.js'
import { createAssetDemand } from '../../resources/js/runtime/assets/asset-demand.js'
import { createBundleRegistry } from '../../resources/js/runtime/assets/bundle-registry.js'
import { createFffLoadHandler, resolveFffLoadComponentId } from '../../resources/js/core/flex-fff-load-directive.js'
import { getAlpineLoadGate, resetAlpineLoadGateForTests } from '../../resources/js/core/flex-alpine-load-gate.js'
import { createDom, createAssetBatch } from '../../tests/js/helpers/flex-field-asset-injector-dom.mjs'

const proofs = [], errors = []
const deferred = () => { let resolve, reject; const promise = new Promise((a,b) => { resolve=a; reject=b }); return {promise,resolve,reject} }
const settle = async () => { for (let i=0;i<16;i++) await Promise.resolve() }
function fixture() {
    const dom = createDom()
    // Existing lightweight DOM helper omits timezone attributes; extend only
    // this diagnostic fixture. Native DOM behaviour is checked in Chromium.
    const create = dom.document.createElement
    dom.document.createElement = tag => {
        const el = create(tag), matches = el.matches.bind(el)
        el.matches = selector => selector === '[data-fff-timezone-boot]' ? el.hasAttribute('data-fff-timezone-boot') : matches(selector)
        return el
    }
    const field = (id='acme') => {
        const root=dom.document.createElement('section'), element=dom.document.createElement('div')
        root.appendChild(createAssetBatch([],[],{consumerComponent:id})); root.appendChild(element); dom.body.appendChild(root)
        return {root,element}
    }
    const marker = (field,name) => { const el=dom.document.createElement('div'); el.setAttribute(name,''); field.root.appendChild(el); return el }
    return {...dom,field,marker}
}
process.on('unhandledRejection', error => errors.push(String(error)))
{
    const f=fixture(), calls=[], pending=deferred()
    const presentation=createFilamentFieldPresentation({loadSegment:()=>{calls.push('load');return pending.promise},loadTimezone:()=>{throw Error('unused timezone')}})
    const plain=f.field(); await presentation.prepare(plain.root); assert.deepEqual(calls,[])
    const first=f.field(), segment=f.marker(first,'data-fff-segment-overflow')
    await presentation.prepare(first.root,{isActive:()=>false}); assert.deepEqual(calls,[])
    const a=presentation.prepare(first.root), b=presentation.prepare(first.root); await settle()
    assert.deepEqual(calls,['load'])
    pending.resolve({prepare:node=>{if(node.dataset.ssrScrollPositioned!=='true'){calls.push(node);node.dataset.ssrScrollPositioned='true'}},preserveMorph(){}})
    await Promise.all([a,b]); assert.deepEqual(calls,['load',segment]); await presentation.prepare(first.root); assert.equal(calls.length,2)
    presentation.dispose(); proofs.push('plain/dormant instances request no domain module; active concurrent preparation shares one import and one idempotent paint')
}
{
    const f=fixture(), calls=[], parent=f.field('parent'), child=f.field('child')
    parent.root.appendChild(child.root)
    const a=f.marker(parent,'data-fff-segment-overflow'), b=f.marker(child,'data-fff-segment-overflow'), z=f.marker(child,'data-fff-timezone-boot'); z.hidden=true
    const p=createFilamentFieldPresentation({loadSegment:async()=>({prepare:el=>calls.push(el),preserveMorph(){}}),loadTimezone:async()=>({prepare:el=>calls.push(el)})})
    await p.prepare(parent.root,{instanceOnly:true}); assert.deepEqual(calls,[a])
    await p.prepare(child.root,{instanceOnly:true,isActive:el=>el!==z}); assert.deepEqual(calls,[a,b,z]); p.dispose()
    proofs.push('exact instance preparation excludes nested child plans; hidden timezone metadata follows its containing field activation')
}
{
    for(const retire of ['abort','disconnect','deactivate','dispose']) {
        const f=fixture(), field=f.field(), marker=f.marker(field,'data-fff-segment-overflow'), task=deferred(), calls=[]
        const controller=new AbortController(); let active=true
        const p=createFilamentFieldPresentation({loadSegment:()=>task.promise})
        const operation=p.prepare(field.root,{signal:controller.signal,isActive:()=>active}); await settle()
        if(retire==='abort') controller.abort(); if(retire==='disconnect') marker.remove(); if(retire==='deactivate') active=false; if(retire==='dispose') p.dispose()
        task.resolve({prepare:el=>calls.push(el),preserveMorph(){}}); await operation; assert.deepEqual(calls,[]); p.dispose()
    }
    const f=fixture(), field=f.field(); f.marker(field,'data-fff-segment-overflow'); let attempt=0
    const p=createFilamentFieldPresentation({loadSegment:async()=>{if(!attempt++)throw Error('load failed');return {prepare(){},preserveMorph(){}}}})
    await assert.rejects(p.prepare(field.root),/load failed/); await p.prepare(field.root); assert.equal(attempt,2); p.dispose()
    proofs.push('aborted/detached/inactive/disposed targets reject late paint; current import failure remains visible and a new request retries')
}
{
    const key=Symbol.for('fff.first-paint.segment.v1'), previous=globalThis[key], calls=[]
    try {
        globalThis[key]={preservePositionedSegmentOverflow:(...args)=>calls.push(args),preserveSegmentPresentation:(...args)=>calls.push(args)}
        const p=createFilamentFieldPresentation({loadSegment:()=>{throw Error('sync morph must not import')}})
        p.preserveMorph('live','incoming'); assert.deepEqual(calls,[['live','incoming'],['live','incoming']]); p.dispose(); p.preserveMorph('old','late'); assert.equal(calls.length,2)
    } finally { if(previous)globalThis[key]=previous;else delete globalThis[key] }
    proofs.push('synchronous native morph delegates to the already installed SSR owner without new imports or a copied implementation')
}
{
    const f=fixture(), parent=f.field('a'), child=f.field('b'); parent.root.appendChild(child.root)
    child.element.setAttribute('x-fff-load-src','/components/a.js'); assert.equal(declaredComponentIdentity(child.element),'b')
    child.element.dataset.fffAssetConsumer='a'; assert.throws(()=>declaredComponentIdentity(child.element),/Conflicting/)
    const orphan=f.document.createElement('div'); orphan.setAttribute('x-fff-load-src','/components/select-field.js'); f.body.appendChild(orphan)
    assert.equal(declaredComponentIdentity(orphan),null); assert.equal(resolveFffLoadComponentId(orphan),'select-field')
    resetAlpineLoadGateForTests(); const gate=getAlpineLoadGate(); let imports=0;gate.importModule=async()=>imports++
    const alpine={prefixed:n=>'x-'+n,skipDuringClone:callback=>(...args)=>callback(...args)}
    const handler=createFffLoadHandler(alpine); handler.inline(orphan)
    await handler(orphan,{modifiers:[]},{cleanup(){}}); assert.equal(orphan._x_async,'failed'); assert.equal(imports,0)
    proofs.push('declared ownership ignores filenames and wrapper classes; conflicting identity rejects; historical helper stays explicit and the production directive fails before importing orphan JS')
}
{
    const f=fixture(); let disposed=0, prepared=0
    const p={prepare:async()=>prepared++,preserveMorph(){},dispose(){disposed++}}
    const runtime=createAssetRuntime({...f,presentation:p}); await runtime.ensureAssets(f.body); runtime.dispose(); runtime.dispose()
    assert.equal(prepared,1);assert.equal(disposed,1)
    const replacement=createFlexFieldAssetInjector({...f,presentation:null}); await replacement.ensureAssets(f.body); replacement.dispose();assert.equal(prepared,1)
    assert.throws(()=>createAssetRuntime({...f,presentation:{}}),/presentation adapter/)
    for(const file of ['asset-runtime-engine.js','filament/livewire-adapter.js','filament/partial-assets.js']) {
        const source=fs.readFileSync(new URL('../../resources/js/runtime/'+file,import.meta.url),'utf8')
        assert(!/segment-overflow-position|timezone-browser-ssr-boot|bootSegmentOverflow|bootTimezoneBrowser/.test(source))
    }
    proofs.push('engine accepts an explicit or disabled presentation adapter; adapter is disposed once; resource orchestration and Livewire policy contain no component domain imports')
}
{
    for(const retire of ['none','dispose','detach','deactivate','plan-change']) {
        const f=fixture(), field=f.field(), task=deferred();let active=true, reached=null
        const demand=createAssetDemand({...f,registry:createBundleRegistry(),graph:{resyncFromDom(){}},store:{disposed:false,discover(){},registerSizes(){},load:async()=>{}},hooks:{},isActiveBatch:()=>active,
            afterEnsure:(root,context)=>{reached={root,context};return task.promise}})
        const operation=demand.awaitBundleReady('acme',field.element); await settle(); assert.equal(reached.root,field.root); assert.equal(reached.context.instanceOnly,true)
        if(retire==='dispose')demand.dispose();if(retire==='detach')field.root.remove();if(retire==='deactivate')active=false
        if(retire==='plan-change')field.root.children[0].setAttribute('data-fff-chunks','["/changed.js"]')
        task.resolve(); assert.equal(await operation,retire==='none'); demand.dispose()
    }
    proofs.push('presentation barrier uses the precise instance shell and rejects success after disposal, detach, deactivation or a plan revision')
}
{
    for(const stale of [false,true]) {
        const f=fixture(), task=deferred(), field=f.field()
        const demand=createAssetDemand({...f,registry:createBundleRegistry(),graph:{resyncFromDom(){}},store:{disposed:false,discover(){},registerSizes(){}},hooks:{},isActiveBatch:()=>true,afterEnsure:()=>task.promise})
        const operation=demand.awaitBundleReady('acme',field.element); await settle(); if(stale)demand.dispose();task.reject(Error('presentation failed'))
        if(stale)assert.equal(await operation,false);else await assert.rejects(operation,/presentation failed/);demand.dispose()
    }
    proofs.push('current presentation failure prevents readiness; retired failure is discarded without unhandled rejection')
}
await settle();assert.deepEqual(errors,[])
console.log(JSON.stringify({groups:proofs.length,proofs,unhandledRejections:errors},null,2))
