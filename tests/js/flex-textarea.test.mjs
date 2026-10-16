import assert from 'node:assert/strict'
import { test } from 'node:test'
import factory from '../../resources/js/components/flex-textarea.js'
import { createResourceScope } from '../../resources/js/runtime/lifecycle/resource-scope.js'
import { createTextareaAutosize } from '../../resources/js/textarea/autosize.js'
import { createTextareaEmoji } from '../../resources/js/textarea/emoji.js'

async function fixture(t, overrides = {}) {
    const saved = { window: globalThis.window, Alpine: globalThis.Alpine }
    const watchers = new Set(), listeners = new Map()
    const input = { value: 'Hello 😀', style: {}, maxLength: 20, isConnected: true, disabled: false, readOnly: false, selectionStart: 0, selectionEnd: 0,
        focus() {}, setSelectionRange(a,b) { this.selectionStart=a;this.selectionEnd=b },
        addEventListener: (name,fn) => listeners.set(name,fn), removeEventListener: name => listeners.delete(name),
        dispatchEvent(event) { listeners.get(event.type)?.(event) },
        ownerDocument: { defaultView: {} },
    }
    let server = input.value
    globalThis.window = { FffAssetInjector: { isElementActive: () => true } }
    globalThis.Alpine = { watch: (getter, callback) => { const record={getter,callback}; watchers.add(record);return()=>watchers.delete(record) } }
    const vm = factory({ initialState: input.value, statePath: 'data.text', shouldAutosize: false, editable: true, characterLimit: 20, messages: {}, ...overrides })
    vm.$el = { isConnected: true }; vm.$refs = { input }; vm.$wire = { $get: () => server }
    await vm.init()
    t.after(()=>{vm.destroy();globalThis.window=saved.window;globalThis.Alpine=saved.Alpine})
    return { vm, input, watchers, listeners,
        edit(value) { input.value=value;input.dispatchEvent(new Event('input')); },
        reset(value) { server=value;for(const item of watchers) item.callback(item.getter()) },
    }
}

test('native editing updates a Unicode counter immediately without an additional server write', async t => {
    const f = await fixture(t)
    assert.equal(f.vm.characterCount, 7); assert.equal(f.vm.counterLabel,'7/20')
    f.edit('😀😀 A'); assert.equal(f.vm.characterCount,4);assert.equal(f.vm.state,'😀😀 A')
    f.edit('   ');assert.equal(f.vm.canSubmit,false)
})
test('external resets preserve empty strings and whitespace and refresh the actual input', async t => {
    const f = await fixture(t)
    f.reset('');assert.equal(f.input.value,'');assert.equal(f.vm.characterCount,0)
    f.reset('  ');assert.equal(f.input.value,'  ');assert.equal(f.vm.characterCount,2)
    f.reset(null);assert.equal(f.vm.state,'')
})
test('cursor insertion uses shared editing and a single input event without splitting emoji', async t => {
    const f = await fixture(t)
    f.input.selectionStart=6;f.input.selectionEnd=8
    assert.equal(f.vm.insertToolText('🌊'),true);assert.equal(f.input.value,'Hello 🌊');assert.equal(f.vm.characterCount,7)
    f.input.maxLength=2;f.input.value='';f.input.selectionStart=0;f.input.selectionEnd=0
    f.vm.insertToolText('😀x');assert.equal(f.input.value,'😀')
})
test('readonly/disabled inputs cannot be modified by tools', async t => {
    const f = await fixture(t,{editable:false})
    f.input.readOnly=true;assert.equal(f.vm.insertToolText('x'),false)
    assert.equal(f.vm.canSubmit,false)
})
test('init and destruction are idempotent and remove all watchers and native listeners', async t => {
    const f = await fixture(t);await f.vm.init();assert.equal(f.watchers.size,1)
    f.vm.destroy();f.vm.destroy();assert.equal(f.watchers.size,0);assert.equal(f.listeners.size,0)
    f.reset('late');assert.equal(f.vm.state,'Hello 😀')
})
test('emoji open after disposal cannot resurrect a dropdown', async () => {
    let resolve, opened=0;const loading=new Promise(done=>resolve=done)
    const owner={options:{editable:true,messages:{}},$refs:{input:{}},emojiPickerOpen:false}
    const scope=createResourceScope();const emoji=createTextareaEmoji(owner,scope,()=>loading)
    const opening=emoji.toggle();scope.dispose();resolve({sharedEmojiPicker:{open(){opened++}}});await opening
    assert.equal(opened,0)
})
test('canceling a pending emoji open leaves only the most recent intent active', async () => {
    let resolve, opened=0;const loading=new Promise(done=>resolve=done)
    const owner={options:{editable:true,messages:{}},$refs:{input:{}},emojiPickerOpen:false}
    const scope=createResourceScope();const emoji=createTextareaEmoji(owner,scope,()=>loading)
    const opening=emoji.toggle();await emoji.toggle();resolve({sharedEmojiPicker:{open(){opened++}}});await opening
    assert.equal(opened,0);assert.equal(owner.emojiPickerOpen,false);scope.dispose()
})
test('emoji loading failure reports a retryable status and allows another open', async () => {
    let messages=[],attempts=0
    const owner={options:{editable:true,messages:{emoji_error:'failed'}},$refs:{input:{}},showStatus:m=>messages.push(m)}
    const scope=createResourceScope();const emoji=createTextareaEmoji(owner,scope,async()=>{attempts++;throw Error('network')})
    await emoji.toggle();await emoji.toggle();assert.equal(attempts,2);assert.deepEqual(messages,['failed','failed']);scope.dispose()
})
test('autosize reuses one measuring node, clamps height, coalesces frames and disposes', t => {
    const savedRAF=globalThis.requestAnimationFrame,savedCancel=globalThis.cancelAnimationFrame
    let pending=new Map(),id=0,created=0,removed=0,observe
    globalThis.requestAnimationFrame=callback=>{pending.set(++id,callback);return id}
    globalThis.cancelAnimationFrame=id=>pending.delete(id)
    t.after(()=>{globalThis.requestAnimationFrame=savedRAF;globalThis.cancelAnimationFrame=savedCancel})
    const mirror={style:{},scrollHeight:200,setAttribute(){},remove(){removed++}}
    const input={style:{},value:'text',clientWidth:300,isConnected:true,ownerDocument:{documentElement:{},createElement(){created++;return mirror},defaultView:{getComputedStyle:()=>({fontSize:'16',maxHeight:'100px'}),ResizeObserver:class{constructor(cb){observe=cb}observe(){}disconnect(){}}}}}
    const root={querySelector:()=>({appendChild(){}})},scope=createResourceScope()
    const size=createTextareaAutosize(root,input,{shouldAutosize:true,initialHeightRem:2.25},scope)
    size.measure();assert.equal(input.style.height,'100px');assert.equal(created,1)
    for(let i=0;i<100;i++)size.schedule();assert.equal(pending.size,2) // initial transition restoration + one measure
    for(const [key,fn] of [...pending]){pending.delete(key);fn()}
    observe([{contentRect:{width:300,height:100}}]);for(const [key,fn] of [...pending]){pending.delete(key);fn()}
    observe([{contentRect:{width:300,height:90}}]);assert.equal(pending.size,0) // height animation must not reschedule itself
    mirror.scrollHeight=20;size.measure();assert.equal(input.style.height,'36px');assert.equal(created,1)
    size.schedule();scope.dispose();assert.equal(pending.size,0);assert.equal(removed,1)
})


test('speech availability is known at factory creation before asynchronous activation', t => {
    const previous = globalThis.window
    t.after(() => { globalThis.window = previous })
    globalThis.window = { SpeechRecognition: class {} }
    const supported = factory({ initialState: '', messages: {} })
    assert.equal(supported.speechSupported, true)
    globalThis.window = {}
    assert.equal(factory({ initialState: '', messages: {} }).speechSupported, false)
})
