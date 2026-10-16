import test from 'node:test'
import assert from 'node:assert/strict'
import { createBubbleRuntime } from '../../resources/js/components/bubble-choice/runtime.js'

function fixture(config = {}) {
    const previous = {window:globalThis.window, ResizeObserver:globalThis.ResizeObserver,
        requestAnimationFrame:globalThis.requestAnimationFrame, cancelAnimationFrame:globalThis.cancelAnimationFrame}
    globalThis.window = { FffAssetInjector: { isElementActive: () => config.active !== false } }
    const frames = new Map(), watches = new Map(), observers = new Set()
    let frameId = 0, writes = 0
    globalThis.requestAnimationFrame = callback => {frames.set(++frameId, callback); return frameId}
    globalThis.cancelAnimationFrame = id => frames.delete(id)
    const node = () => {
        const listeners = new Map(), attributes = new Map(), classes = new Set(), styleMap = new Map()
        return { isConnected: true, listeners, style: {setProperty(key,value){writes++; styleMap.set(key,value)},getPropertyValue:key=>styleMap.get(key)??''},
            classList: {add(...keys){keys.forEach(key=>classes.add(key))},toggle(key,on){writes++; on?classes.add(key):classes.delete(key)}},
            setAttribute(key,value){writes++;attributes.set(key,value)},getAttribute:key=>attributes.get(key),
            addEventListener(key, callback){if(!listeners.has(key))listeners.set(key,new Set());listeners.get(key).add(callback)},
            removeEventListener(key, callback){listeners.get(key)?.delete(callback)},
            emit(key, event={}){for(const callback of listeners.get(key)??[])callback(event)} }
    }
    const root = node(), scroll = {...node(), clientWidth:400,clientHeight:300,scrollWidth:560,scrollHeight:340,scrollLeft:0,scrollTop:0}
    const entrances = []
    if (config.webAnimations) scroll.animate = (keyframes, timing) => {
        let resolve
        const animation = { keyframes, timing, cancelled: false, finished: new Promise(done => { resolve = done }),
            cancel(){this.cancelled = true; resolve()}, finish(){resolve()} }
        entrances.push(animation)
        return animation
    }
    const cells = ['a','b','c'].map((key,index)=>{
        const cell = {...node(),dataset:{bubbleValue:key,bubbleRow:Math.floor(index/2),bubbleCol:index%2}}
        const inner=node(),button={...node(),disabled:false,focus(){button.focused=true},closest:()=>cell}
        cell.button=button;cell.inner=inner
        cell.querySelector=selector=>selector==='button'?button:selector==='.fff-bubble-choice__inner'?inner:null
        return cell
    })
    const geometry={ getAttribute:()=> 'M0 0L1 1Z', getTotalLength:()=>3,
        getPointAtLength:length=>({x:.5+.48*Math.cos(length/3*2*Math.PI),y:.5+.48*Math.sin(length/3*2*Math.PI)}) }
    root.querySelector=selector=>selector==='.fff-bubble-choice__scrollable'?scroll:selector==='.fff-bubble-choice__rows'?{querySelectorAll:()=>cells}:geometry
    root.contains=node=>cells.some(cell=>cell.button===node)
    root.closest=()=>null
    const media={...node(),matches:!!config.reduced}
    root.ownerDocument={body:{},documentElement:{dir:config.rtl?'rtl':'ltr'},addEventListener(){},removeEventListener(){},defaultView:{matchMedia:()=>media}}
    globalThis.ResizeObserver=class {constructor(callback){this.callback=callback;observers.add(this)}observe(){}disconnect(){observers.delete(this)}}
    const runtime=createBubbleRuntime({state:[],options:cells.map(cell=>({value:cell.dataset.bubbleValue,label:cell.dataset.bubbleValue})),
        selectedShape:'scallop',maxItems:null,layout:{numCols:3},...config})
    Object.assign(runtime,{$el:root,$watch(key,callback){watches.set(key,callback);return()=>watches.delete(key)}})
    return {runtime,root,scroll,cells,frames,watches,observers,entrances,get writes(){return writes},
        update(key,value){runtime[key]=value;watches.get(key)?.()},
        flush(now=performance.now()+1000){const queue=[...frames.values()];frames.clear();queue.forEach(callback=>callback(now))},
        click(index){const button=cells[index].button;root.emit('click',{target:{closest:()=>button}});watches.get('state')?.()},
        key(index,key){const button=cells[index].button;let prevented=false;root.emit('keydown',{target:{closest:()=>button},key,preventDefault(){prevented=true}});return prevented},
        close(){runtime.destroy();Object.assign(globalThis,previous)} }
}

test('mount is idempotent and exposes ready only after owned presentation exists',async()=>{
    const f=fixture();const a=f.runtime.init();assert.equal(a,f.runtime.init());await a
    assert.equal(f.watches.size,3);assert.equal(f.observers.size,1);assert.equal(f.root.getAttribute('data-bubble-choice-ready'),'');f.flush();f.close()
})
test('delegated selection respects limits, including zero, and reverses rapidly',async()=>{
    const f=fixture({maxItems:1});await f.runtime.init();f.click(0);assert.deepEqual(f.runtime.state,['a']);f.click(1);assert.deepEqual(f.runtime.state,['a'])
    f.click(0);f.click(1);assert.deepEqual(f.runtime.state,['b']);assert.equal(f.frames.size,2);f.flush();assert.equal(f.frames.size,0);f.close()
})
test('100 pan notifications coalesce into one frame and listeners are passive',async()=>{
    const f=fixture();await f.runtime.init();for(let i=0;i<100;i++){f.scroll.scrollTop=i;f.scroll.emit('scroll')}
    assert.equal(f.frames.size,2);f.flush();assert.equal(f.frames.size,0);assert.match(f.cells[0].style.transform,/translate\(/);f.close()
})
test('resize repositions adopted cells without new observers or duplicated buttons',async()=>{
    const f=fixture();await f.runtime.init();f.scroll.clientWidth=320;f.observers.values().next().value.callback()
    assert.equal(f.observers.size,1);assert.equal(f.cells.length,3);assert.ok(Number.isFinite(f.scroll.scrollLeft));assert.ok(!f.cells[0].style.transform.includes('NaN'));f.close()
})
test('keyboard centers and focuses options without changing selection',async()=>{
    const f=fixture();await f.runtime.init();assert.ok(f.key(0,'End'));assert.ok(f.cells[2].button.focused);assert.deepEqual(f.runtime.state,[]);f.close()
})
test('RTL left/right navigation follows logical direction',async()=>{
    const f=fixture({rtl:true});await f.runtime.init();f.key(0,'ArrowLeft');assert.ok(f.cells[1].button.focused);f.close()
})
for(const policy of ['disabled','readOnly'])test(`${policy} ignores forged interaction and still paints server state`,async()=>{
    const f=fixture({[policy]:true});await f.runtime.init();f.click(0);assert.deepEqual(f.runtime.state,[])
    f.update('state',['b']);f.flush();assert.equal(f.cells[1].button.getAttribute('aria-pressed'),'true');f.close()
})
test('1000 identical server echoes perform no writes and create no idle frames',async()=>{
    const f=fixture({state:['a']});await f.runtime.init();f.flush();const before=f.writes
    for(let i=0;i<1000;i++)f.watches.get('state')()
    assert.equal(f.writes,before);assert.equal(f.frames.size,0);f.close()
})
test('reduced motion commits the target without an animation loop',async()=>{
    const f=fixture({reduced:true});await f.runtime.init();f.flush();f.click(0);assert.equal(f.frames.size,0);assert.equal(f.cells[0].button.style.clipPath,'var(--fff-bubble-choice-clip-scallop)');f.close()
})
test('100 mount/destroy cycles retire pending frames, watchers, observers and listeners',async()=>{
    for(let i=0;i<100;i++){const f=fixture();await f.runtime.init();f.click(0);f.scroll.emit('scroll');f.close();f.runtime.destroy()
        assert.equal(f.frames.size,0);assert.equal(f.watches.size,0);assert.equal(f.observers.size,0)
        for(const target of [f.root,f.scroll])for(const listeners of target.listeners.values())assert.equal(listeners.size,0)}
})
test('destroy while inactive cancels activation without mounting an observer',async()=>{
    const f=fixture({active:false});const promise=f.runtime.init();f.runtime.destroy();await promise;assert.equal(f.observers.size,0);assert.equal(f.watches.size,0);f.close()
})
test('entrance starts after geometry, uses only opacity and transform, and is cancelled by its owner',async()=>{
    const f=fixture({webAnimations:true});await f.runtime.init()
    assert.match(f.cells[0].style.transform,/translate\(/)
    assert.equal(f.entrances.length,0);f.flush()
    assert.equal(f.entrances.length,1)
    assert.deepEqual(f.entrances[0].keyframes,[{opacity:0,transform:'scale(.985)'},{opacity:1,transform:'scale(1)'}])
    assert.equal(f.entrances[0].timing.duration,320)
    f.close();assert.equal(f.entrances[0].cancelled,true)
})
test('reduced motion reveals the arena without creating a web animation',async()=>{
    const f=fixture({webAnimations:true,reduced:true});await f.runtime.init();f.flush()
    assert.equal(f.entrances.length,0);assert.equal(f.frames.size,0);f.close()
})
