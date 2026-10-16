import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createDictation } from '../../resources/js/components/text-input/dictation.js'
import { createResourceScope } from '../../resources/js/runtime/lifecycle/resource-scope.js'
import textareaFactory from '../../resources/js/components/flex-textarea.js'
import { createTextInputRuntime } from '../../resources/js/components/text-input/runtime.js'

const final = text => Object.assign([{ transcript: text }], { isFinal: true })
function fixture(t, options = {}) {
    const previous = globalThis.window
    const recognitions = [], statuses = []
    class Recognition {
        constructor() { recognitions.push(this) }
        start() { this.starts = (this.starts ?? 0) + 1 }
        stop() { this.stops = (this.stops ?? 0) + 1 }
        abort() { this.aborts = (this.aborts ?? 0) + 1 }
    }
    const view = new EventTarget(); view.SpeechRecognition = Recognition
    globalThis.window = view
    const field = new EventTarget()
    Object.assign(field, { value: 'base', maxLength: -1, type: 'text', selectionStart: 0, selectionEnd: 4,
        ownerDocument: Object.assign(new EventTarget(), { defaultView: view }),
        setSelectionRange(a,b) { this.selectionStart=a;this.selectionEnd=b }, focus() {} })
    const owner = { $refs: { input: field }, $el: { isConnected: true }, status: '',
        options: { editable: true, speechDictation: true, speechDictationLanguage: 'pl-PL', speechDictationMode: 'append',
            messages: { network:'service unavailable', speech_error:'failed', speech_model_loading:'preparing', speech_model_error:'model failed' }, ...options },
        showStatus(value) { this.status=value;statuses.push(value) } }
    const scope = createResourceScope(), dictation = createDictation(owner,scope)
    t.after(()=>{scope.dispose();globalThis.window=previous})
    return { field, owner, scope, dictation, Recognition, view, recognitions, statuses }
}

test('stop remains busy until final results arrive; repeated clicks do not discard them', t => {
    const f=fixture(t);f.dictation.start();const r=f.recognitions[0]
    f.dictation.stop();f.dictation.start();f.dictation.stop()
    assert.equal(f.recognitions.length,1);assert.equal(r.stops,1);assert.equal(f.owner.isListening,true)
    r.onresult({resultIndex:0,results:[final('ready')]});r.onend()
    assert.equal(f.field.value,'base ready');assert.equal(f.owner.isListening,false)
})
test('repeated final result indexes are deduplicated and committed through one native event', t => {
    const f=fixture(t);let events=0;f.field.addEventListener('input',()=>events++)
    f.dictation.start();const r=f.recognitions[0], results=[final('one '),final('two')]
    r.onresult({resultIndex:0,results});r.onresult({resultIndex:0,results});r.onend()
    assert.equal(f.field.value,'base one two');assert.equal(events,1)
})
test('service error preserves already finalized text, clears the microphone and permits retry', async t => {
    const f=fixture(t);f.dictation.start();const r=f.recognitions[0]
    r.onresult({resultIndex:0,results:[final('valid')]});r.onerror({error:'network'})
    assert.equal(f.field.value,'base valid');assert.equal(f.owner.isListening,false);assert.equal(r.onend,null)
    assert.equal(f.owner.status,'service unavailable');f.dictation.start();assert.equal(f.recognitions.length,2)
})
test('a new field owns the microphone without letting the old owner revoke its session', t => {
    const f=fixture(t), field={...f.field,value:'second',ownerDocument:f.field.ownerDocument}, secondScope=createResourceScope()
    const owner={...f.owner,$refs:{input:field}}, second=createDictation(owner,secondScope)
    t.after(()=>secondScope.dispose());f.dictation.start();const first=f.recognitions[0]
    const stale=first.onresult;second.start();assert.equal(f.owner.isListening,false);assert.equal(first.aborts,1)
    stale({resultIndex:0,results:[final('stale')]});assert.equal(f.field.value,'base')
    f.scope.dispose();assert.equal(owner.isListening,true);assert.equal(f.recognitions[1].aborts,undefined)
})
test('constructor/start exceptions and synchronous native errors cannot leave a recording indicator stuck', t => {
    const f=fixture(t)
    f.view.SpeechRecognition=class{constructor(){throw Error('constructor')}}
    assert.doesNotThrow(()=>f.dictation.start());assert.equal(f.owner.isListening,false)
    f.view.SpeechRecognition=class extends f.Recognition{start(){this.onerror({error:'network'})}}
    f.dictation.start();assert.equal(f.owner.isListening,false);assert.equal(f.owner.status,'service unavailable')
})
test('read-only, disabled and removed owners reject late recognition results', t => {
    const f=fixture(t);f.dictation.start();const r=f.recognitions[0]
    f.field.readOnly=true;r.onresult({resultIndex:0,results:[final('discard')]})
    assert.equal(f.owner.isListening,false);assert.equal(f.field.value,'base')
    f.field.readOnly=false;f.field.disabled=true;f.dictation.start();assert.equal(f.recognitions.length,1)
    f.field.disabled=false;f.owner.$el.isConnected=false;f.dictation.start();assert.equal(f.recognitions.length,1)
})
test('document hiding releases a recording session and all recognition callbacks', t => {
    const f=fixture(t);f.dictation.start();const r=f.recognitions[0]
    f.field.ownerDocument.hidden=true;f.field.ownerDocument.dispatchEvent(new Event('visibilitychange'))
    assert.equal(f.owner.isListening,false);assert.equal(r.onresult,null);assert.equal(r.aborts,1)
})

for (const type of ['textarea','input']) test(`${type} runtime writes recognized text through native state, with immediate metadata`, async t => {
    const f=fixture(t), previous={ document:globalThis.document, MutationObserver:globalThis.MutationObserver, Alpine:globalThis.Alpine }
    globalThis.document=f.field.ownerDocument;globalThis.MutationObserver=class{observe(){}disconnect(){}}
    globalThis.Alpine={watch:()=>()=>{}}
    f.view.FffAssetInjector={isElementActive:()=>true}
    const options={...f.owner.options,shouldAutosize:false,initialState:'base',statePath:'data.message',counterMode:'characters',showCharacterCounter:true,characterLimit:100}
    let vm
    if(type==='textarea') {vm=textareaFactory(options);vm.$wire={$get:()=>f.field.value}}
    else vm=createTextInputRuntime()
    vm.$el={isConnected:true,dataset:{fffTextInputOptions:JSON.stringify(options)}};vm.$refs={input:f.field}
    t.after(()=>{vm.destroy();Object.assign(globalThis,previous)})
    await vm.init();await vm.toggleDictation();const r=f.recognitions[0]
    r.onresult({resultIndex:0,results:[final('message')]});await vm.toggleDictation();r.onend()
    assert.equal(f.field.value,'base message');assert.equal(vm.characterCount,12);assert.equal(vm.isListening,false)
    assert.equal(type==='textarea'?vm.state:vm.value,'base message')
})


test('an unresponsive recognizer cannot leave a stopped field permanently busy', t => {
    const f=fixture(t), previousTimeout=globalThis.setTimeout, previousClear=globalThis.clearTimeout
    let finish
    globalThis.setTimeout=(callback, delay)=>{assert.equal(delay,5000);finish=callback;return 1}
    globalThis.clearTimeout=()=>{}
    t.after(()=>{globalThis.setTimeout=previousTimeout;globalThis.clearTimeout=previousClear})
    f.owner.options.messages.speech_timeout='finalization timed out'
    f.dictation.start();const r=f.recognitions[0]
    r.onresult({resultIndex:0,results:[final('saved')]});f.dictation.stop();finish()
    assert.equal(f.owner.isListening,false);assert.equal(r.aborts,1);assert.equal(f.field.value,'base saved')
    assert.equal(f.owner.status,'finalization timed out')
})
