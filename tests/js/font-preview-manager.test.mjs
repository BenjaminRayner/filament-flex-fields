import assert from 'node:assert/strict'
import {test} from 'node:test'
import {createFontPreviewManager} from '../../resources/js/fonts/manager.js'
import {visibleFontDemand} from '../../resources/js/fonts/visible-demand.js'
import {loadFontPreview,assertFontResource} from '../../resources/js/fonts/transport.js'
import {createFontOptionSource} from '../../resources/js/select/adapters/font-select/source.js'
import {createVirtualWindowSession} from '../../resources/js/select/virtualization/virtual-window-session.js'

const origin='https://app.example'
const resource=(n,selected=false)=>({key:n.toString(16).padStart(64,'0'),kind:'file',url:origin+'/fonts/'+n+'.woff2',text:'Preview',weight:400,style:'normal',selected})
const tick=()=>new Promise(resolve=>setImmediate(resolve))
function harness(concurrency=4) {
    const jobs=[],added=new Set(),removed=[],frames=[]
    const manager=createFontPreviewManager({origin,concurrency,load:(r,signal)=>new Promise((resolve,reject)=>{
        jobs.push({r,signal,resolve:()=>resolve({face:{id:r.key},alias:'private-'+r.key}),reject})
        signal.addEventListener('abort',()=>reject(new DOMException('Aborted','AbortError')),{once:true})
    }),add:face=>added.add(face),remove:face=>{removed.push(face);added.delete(face)},
        frame:callback=>{frames.push(callback);return frames.length},cancelFrame:()=>{}})
    return {manager,jobs,added,removed,flush:()=>{for (const callback of frames.splice(0)) callback()}}
}

test('deduplicates two owners and deletes only the last released private face',async()=>{
    const h=harness(),a=Symbol(),b=Symbol(),external={id:'panel-font'}
    h.added.add(external)
    h.manager.replaceDemand(a,[resource(1)],()=>{})
    h.manager.replaceDemand(b,[resource(1)],()=>{})
    await tick();assert.equal(h.jobs.length,1)
    h.jobs[0].resolve();await tick();assert.equal(h.manager.read(resource(1).key).status,'ready')
    h.manager.releaseOwner(a);assert.equal(h.removed.length,0)
    h.manager.releaseOwner(b);assert.equal(h.removed.length,1);assert.ok(h.added.has(external))
    assert.equal(h.manager.snapshot().resources,0)
})

test('prioritizes selections, bounds concurrency and aborts departed previews',async()=>{
    const h=harness(2),a=Symbol()
    h.manager.replaceDemand(a,[resource(1),resource(2),resource(3,true)],()=>{})
    await tick();assert.deepEqual(h.jobs.map(j=>j.r.key),[resource(3).key,resource(1).key])
    h.manager.replaceDemand(a,[resource(3,true),resource(4)],()=>{})
    await tick();assert.ok(h.jobs[1].signal.aborted);assert.equal(h.jobs.length,3)
    assert.ok(h.manager.snapshot().flights<=2)
    h.manager.releaseOwner(a);await tick();assert.equal(h.manager.snapshot().flights,0)
    assert.equal(h.manager.snapshot().resources,0)
})

test('a late decoded resource can never be re-added after release',async()=>{
    const added=[];let finish
    const manager=createFontPreviewManager({origin,load:()=>new Promise(resolve=>{finish=resolve}),add:face=>added.push(face),remove:()=>{},frame:()=>1,cancelFrame:()=>{}})
    const owner=Symbol();manager.replaceDemand(owner,[resource(1)],()=>{});await tick()
    manager.releaseOwner(owner);finish({face:{},alias:'late'});await tick()
    assert.equal(added.length,0);assert.equal(manager.snapshot().resources,0)
})

test('isolates failures, supports retry and coalesces readiness notifications',async()=>{
    const h=harness(),owner=Symbol();let updates=0
    h.manager.replaceDemand(owner,[resource(1),resource(2)],()=>updates++)
    await tick();h.jobs[0].reject(new Error('offline'));h.jobs[1].resolve();await tick();h.flush()
    assert.equal(updates,1);assert.equal(h.manager.read(resource(1).key).status,'failed')
    h.manager.retry(resource(1).key);await tick();h.jobs[2].resolve();await tick();h.flush()
    assert.equal(h.manager.read(resource(1).key).status,'ready');h.manager.releaseOwner(owner)
})

test('random lease changes retain no abandoned resources or owner notifications',async()=>{
    const h=harness(),owners=Array.from({length:8},()=>Symbol());let seed=11
    for (let step=0;step<1000;step++) {
        seed=(seed*1664525+1013904223)>>>0
        const owner=owners[seed%8]
        h.manager.replaceDemand(owner,seed%3 ? [resource(seed%12)] : [],()=>{})
        assert.ok(h.manager.snapshot().resources<=12)
    }
    for (const owner of owners) h.manager.releaseOwner(owner)
    await tick();assert.equal(h.manager.snapshot().owners,0);assert.equal(h.manager.snapshot().resources,0)
})

test('visible demand excludes overscan and scales with viewport, not 10000 families',()=>{
    const session=createVirtualWindowSession(),rows=Array.from({length:10000},(_,i)=>({key:String(i),type:'option',option:{font:resource(i+1)}}))
    const started=performance.now()
    for(let offset=0;offset<640000;offset+=1920) {
        const window=session.read({count:rows.length,revision:'fixed',scrollTop:offset,viewportHeight:320,viewportWidth:400,estimateSize:()=>64,overscan:10})
        const demand=visibleFontDemand(rows,window.virtualItems,offset,320)
        assert.ok(demand.length<=5);assert.ok(window.virtualItems.length<=25)
    }
    assert.ok(performance.now()-started<1000);session.dispose()
    assert.deepEqual(visibleFontDemand(rows,[{index:0,start:0,end:64},{index:1,start:64,end:128}],84,64,20).map(r=>r.key),[resource(2).key])
})

test('transport decodes only verified bounded font data under a private alias',async()=>{
    const calls=[],r={...resource(1),kind:'google-css',url:'https://fonts.googleapis.com/css2?family=Inter&text=Preview'}
    class Face {constructor(family,bytes){this.family=family;this.bytes=bytes;this.status='unloaded'} async load(){this.status='loaded';return this}}
    const fetch=async url=>{calls.push(url);return new Response(calls.length===1 ? "@font-face {src: url(https://fonts.gstatic.com/l/font?kit=abc) format('woff2')}" : new Uint8Array([119,79,70,50,0]),{status:200})}
    const result=await loadFontPreview(r,new AbortController().signal,{origin,fetch,Font:Face})
    assert.equal(calls.length,2);assert.match(result.alias,/^fff-ext-font-preview--font-/);assert.equal(result.face.status,'loaded')
})

test('rejects credential URLs, untrusted origins, HTML and oversized responses',async()=>{
    for(const url of ['https://evil.example/x.woff2','data:font/woff2,abc','https://user:pass@app.example/x.woff2']) {
        assert.throws(()=>assertFontResource({...resource(1),url},origin))
    }
    for(const response of [new Response('<html>'),new Response('x',{headers:{'content-length':'1000000'}})]) {
        await assert.rejects(loadFontPreview(resource(1),new AbortController().signal,{origin,fetch:async()=>response,Font:class{}}))
    }
})

test('font source rejects invalid pages and never publishes a disposed response',async()=>{
    let finish
    const source=createFontOptionSource({origin,perPage:1,category:()=>'',request:()=>new Promise(resolve=>{finish=resolve})})
    const flight=source.search({query:'',cursor:null,signal:new AbortController().signal});source.dispose()
    finish({items:[{value:'local:test',label:'Test',font:resource(1)}],hasMore:false,cursor:null})
    await assert.rejects(flight,{name:'AbortError'})
})

test('font panel width is not replaced by narrow trigger width after anchoring',async()=>{
    const {createLayoutPositionMixin}=await import('../../resources/js/select/adapters/layout/position.js')
    const calls=[]
    const position=createLayoutPositionMixin({selectMenu:{updateMenuPosition(){calls.push('anchor')}}})
    const field={matchTriggerWidth:false,canOptionLabelsWrap:false,isGridLayout:false,
        resolveMenuElement:()=>({}),resolveMenuTriggerElement:()=>({}),
        applyHeadlessDropdownWidthLayout:()=>calls.push('width'),applyPlainListDropdownWidth:()=>calls.push('narrow'),
        isSheetPresentation:()=>false}
    position.updateMenuPosition.call(field)
    assert.deepEqual(calls,['width','anchor'])
})

test('compact font panels remain outside the trigger when vertical space is limited',async()=>{
    const {resolveFontMenuHeight}=await import('../../resources/js/fonts/menu-geometry.js')
    assert.equal(resolveFontMenuHeight({top:403,bottom:445},{top:80,bottom:704},360),317)
    assert.equal(resolveFontMenuHeight({top:100,bottom:142},{top:80,bottom:704},360),360)
    assert.equal(resolveFontMenuHeight({top:403,bottom:445},{top:80,bottom:704},360,6,'bottom'),253)
})

test('aborting a decode wait releases the flight even when native decoding is pending',async()=>{
    class PendingFace {load(){return new Promise(()=>{})}}
    const controller=new AbortController()
    const flight=loadFontPreview(resource(1),controller.signal,{origin,fetch:async()=>new Response(new Uint8Array([119,79,70,50])),Font:PendingFace})
    await tick();controller.abort();await assert.rejects(flight,{name:'AbortError'})
})

test('owner lifecycle suspends hidden tabs, retains suspended parents and reconnects after body replacement',async()=>{
    const {bindFontOwner}=await import('../../resources/js/fonts/owner-lifecycle.js')
    const previousWindow=globalThis.window,events=new Map(),frames=[],observed=[]
    let mutation,disconnections=0,stack=['modal:parent']
    const classes=values=>({contains:value=>values.includes(value)})
    const body={},parent={id:'parent',classList:classes(['fi-modal']),contains:node=>node===root},
        panel={classList:classes(['fi-modal-window']),style:{display:'none'},parentElement:parent},
        root={isConnected:true,classList:classes([]),style:{},parentElement:panel,closest:selector=>selector==='.fi-modal' ? parent : null}
    const doc={body,defaultView:{MutationObserver:class{constructor(callback){mutation=callback}observe(node){observed.push(node)}disconnect(){disconnections++}},
        requestAnimationFrame:callback=>{frames.push(callback);return frames.length},cancelAnimationFrame:()=>{}},
        addEventListener:(name,callback)=>events.set(name,callback),removeEventListener:name=>events.delete(name)}
    root.ownerDocument=doc
    globalThis.window={FffAssetInjector:{getModalOpenStack:()=>stack}}
    const values=[],flush=()=>{for(const callback of frames.splice(0)) callback()}
    try {
        const release=bindFontOwner(root,value=>values.push(value))
        assert.equal(values.at(-1),true) // Hidden suspended parent window retains its lease.
        root.hidden=true;mutation([{type:'attributes',attributeName:'hidden',target:root}]);flush();assert.equal(values.at(-1),false)
        root.hidden=false;mutation([{type:'attributes',attributeName:'hidden',target:root}]);flush();assert.equal(values.at(-1),true)
        const count=values.length
        mutation([{type:'childList',removedNodes:[],addedNodes:[{contains:()=>false}]}]);flush();assert.equal(values.length,count)
        doc.body={};events.get('livewire:navigated')();flush();assert.equal(observed.at(-1),doc.body)
        stack=[];events.get('x-modal-closed')();flush();assert.equal(values.at(-1),false)
        release();release();assert.equal(events.size,0);assert.ok(disconnections>=2)
    } finally {globalThis.window=previousWindow}
})
