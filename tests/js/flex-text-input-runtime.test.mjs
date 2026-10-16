import assert from 'node:assert/strict'
import { test } from 'node:test'
import { countText, counterLabel } from '../../resources/js/components/text-input/counters.js'
import { insertInputText, replaceInputValue } from '../../resources/js/components/text-input/editing.js'
import { createTextInputRuntime } from '../../resources/js/components/text-input/runtime.js'
import { createDictation } from '../../resources/js/components/text-input/dictation.js'
import { createResourceScope } from '../../resources/js/runtime/lifecycle/resource-scope.js'

class Input extends EventTarget {
    value = ''; type = 'text'; maxLength = -1; disabled = false; readOnly = false
    selectionStart = 0; selectionEnd = 0
    focus() { this.focused = true }
    select() { this.setSelectionRange(0, this.value.length) }
    setSelectionRange(start, end) { this.selectionStart = start; this.selectionEnd = end }
}

test('Unicode counts preserve code points, graphemes, words and UTF-8 bytes', () => {
    assert.equal(countText('A😀é'), 3)
    assert.equal(countText('A😀é', 'bytes'), 7)
    assert.equal(countText('👨‍👩‍👧‍👦', 'graphemes'), 1)
    assert.equal(countText(' one  two ', 'words'), 2)
    assert.equal(counterLabel(3, 5, 'both'), '3/5 · 2')
})

test('insert replaces selection and emits exactly one event without breaking surrogate pairs', () => {
    const input = new Input(); input.value = 'abcd'; input.maxLength = 5
    input.setSelectionRange(1, 3)
    let events = 0; input.addEventListener('input', () => events++)
    insertInputText(input, '😀😀')
    assert.equal(input.value, 'a😀d'); assert.equal(events, 1)
    assert.equal(input.selectionStart, 3)
    input.disabled = true
    assert.equal(replaceInputValue(input, ''), false); assert.equal(input.value, 'a😀d')
    input.disabled = false; input.readOnly = true
    assert.equal(insertInputText(input, 'x'), false)
})

test('email and numeric inputs never invoke unsupported selection APIs', () => {
    const input = new Input(); input.type = 'email'; input.value = 'a@b.test'
    input.setSelectionRange = () => { throw new Error('InvalidStateError') }
    assert.equal(insertInputText(input, 'x'), true)
    assert.equal(input.value, 'a@b.testx')
})

test('runtime initializes once, preserves initial whitespace, defers IME metadata and disposes all listeners', async () => {
    const oldDocument = globalThis.document; const oldObserver = globalThis.MutationObserver
    let observers = 0; let disconnected = 0
    globalThis.document = new EventTarget()
    globalThis.MutationObserver = class { constructor() { observers++ } observe() {} disconnect() { disconnected++ } }
    try {
        const input = new Input(); input.value = '  draft  '
        const owner = createTextInputRuntime()
        owner.$el = { dataset: { fffTextInputOptions: JSON.stringify({ editable: true, caretOnFocus: 'preserve', counterMode: 'characters' }) } }
        owner.$refs = { input }
        assert.equal(owner.init(), owner.init()); await owner.init()
        assert.equal(observers, 1); assert.equal(owner.value, '  draft  ')
        input.dispatchEvent(new Event('compositionstart')); input.value = '中'; input.dispatchEvent(new Event('input'))
        assert.equal(owner.value, '  draft  ')
        input.dispatchEvent(new Event('compositionend')); assert.equal(owner.value, '中')
        owner.destroy(); owner.destroy(); assert.equal(disconnected, 1)
        input.value = 'detached'; input.dispatchEvent(new Event('input')); assert.equal(owner.value, '中')
    } finally { globalThis.document = oldDocument; globalThis.MutationObserver = oldObserver }
})

test('dictation commits final transcript once and never commits after owner disposal', () => {
    const oldWindow = globalThis.window; const oldNavigator = Object.getOwnPropertyDescriptor(globalThis, 'navigator')
    let latest
    class Recognition { constructor() { latest = this } start() {} stop() { this.onend?.() } abort() { this.aborted = true } }
    globalThis.window = { SpeechRecognition: Recognition }
    Object.defineProperty(globalThis, 'navigator', { value: { language: 'en' }, configurable: true })
    try {
        const input = new Input(); input.value = 'hello'
        let events = 0; input.addEventListener('input', () => events++)
        const owner = { $refs: { input }, $el: { isConnected: true }, options: { editable: true,
            speechDictationMode: 'append', speechDictationContinuous: false, messages: {} }, showStatus() {} }
        const scope = createResourceScope(); const dictation = createDictation(owner, scope)
        dictation.start(); latest.onresult({ resultIndex: 0, results: [Object.assign([{ transcript: 'world' }], { isFinal: true })] })
        dictation.stop(); assert.equal(input.value, 'hello world'); assert.equal(events, 1)
        dictation.start(); latest.onresult({ resultIndex: 0, results: [Object.assign([{ transcript: 'discard' }], { isFinal: true })] })
        const abandoned = latest; scope.dispose(); assert.equal(abandoned.aborted, true)
        assert.equal(abandoned.onend, null); assert.equal(events, 1); assert.equal(owner.isListening, false)
    } finally {
        globalThis.window = oldWindow
        if (oldNavigator) Object.defineProperty(globalThis, 'navigator', oldNavigator); else delete globalThis.navigator
    }
})

test('clipboard refusal produces an error instead of false success', async () => {
    const { copyText } = await import('../../resources/js/components/text-input/clipboard.js')
    const previous = Object.getOwnPropertyDescriptor(globalThis, 'navigator')
    Object.defineProperty(globalThis, 'navigator', { configurable: true, value: { clipboard: { writeText: async () => { throw new Error('Denied') } } } })
    try { await assert.rejects(copyText({ value: 'sample' }), /Denied/) }
    finally { if (previous) Object.defineProperty(globalThis, 'navigator', previous); else delete globalThis.navigator }
})

test('a too-large emoji cannot erase a selected character at the native maxlength boundary', () => {
    const input = new Input(); input.value = 'abc'; input.maxLength = 3; input.setSelectionRange(1, 2)
    assert.equal(insertInputText(input, '😀'), false)
    assert.equal(input.value, 'abc'); assert.equal(input.selectionStart, 1)
})


test('empty clear-only tools disappear and default focus preserves the exact caret/selection', async () => {
    const oldDocument = globalThis.document; const oldObserver = globalThis.MutationObserver
    globalThis.document = new EventTarget()
    globalThis.MutationObserver = class { observe() {} disconnect() {} }
    try {
        const input = new Input(); input.value = 'abc'; input.setSelectionRange(1, 1)
        const owner = createTextInputRuntime()
        owner.$el = { dataset: { fffTextInputOptions: JSON.stringify({ editable: true, clearable: true,
            persistentTools: false, caretOnFocus: 'preserve' }) } }
        owner.$refs = { input }; await owner.init()
        input.dispatchEvent(new Event('focus'))
        assert.equal(input.selectionStart, 1); assert.equal(input.selectionEnd, 1)
        assert.equal(owner.shouldShowActionGroup, true)
        owner.clearInput()
        assert.equal(input.value, ''); assert.equal(owner.shouldShowActionGroup, false)
        owner.options.persistentTools = true
        assert.equal(owner.shouldShowActionGroup, true)
        owner.destroy()
    } finally { globalThis.document = oldDocument; globalThis.MutationObserver = oldObserver }
})

test('shared menu outside dismissal respects shadow content, child portals and listener disposal', async () => {
    const { createMenuListenersMixin } = await import('../../resources/js/overlay/menu/listeners.js')
    const oldDocument = globalThis.document; const oldWindow = globalThis.window
    globalThis.document = new EventTarget(); globalThis.window = new EventTarget()
    document.defaultView = window; document.documentElement = {}; document.activeElement = null
    try {
        const trigger = { nodeType: 1, ownerDocument: document, isConnected: true, contains: () => false }
        const menu = { nodeType: 1, ownerDocument: document, isConnected: true, contains: () => false, dataset: { fffDropdownOwner: 'owner' } }
        let closed = 0
        const owner = { ...createMenuListenersMixin({ openKey: 'open', dismissOutside: true,
            scrollHandlerKey: 'scroll', resizeHandlerKey: 'resize', scrollParentsKey: 'parents' }),
            open: true, __fffOverlayManaged: true, resolveMenuTriggerRef: () => trigger, resolveMenuElement: () => menu,
            closeTeleportedMenu() { closed++ } }
        owner.bindMenuListeners()
        const click = path => { const event = new Event('pointerdown'); event.composedPath = () => path; window.dispatchEvent(event) }
        click([trigger]); click([{}, menu]); click([{ nodeType: 1, closest: selector => selector === '[data-fff-dropdown-parent]' ? { dataset: { fffDropdownParent: 'owner' } } : null }])
        assert.equal(closed, 0)
        click([{ nodeType: 1 }, {}]); assert.equal(closed, 1)
        owner.unbindMenuListeners(); click([{ nodeType: 1 }, {}]); assert.equal(closed, 1)
    } finally { globalThis.document = oldDocument; globalThis.window = oldWindow }
})

test('queued option refresh after disposal cannot reinstall input bindings', async () => {
    const oldDocument = globalThis.document, oldObserver = globalThis.MutationObserver
    globalThis.document = new EventTarget(); globalThis.MutationObserver = class { observe() {} disconnect() {} }
    try {
        const runtime = createTextInputRuntime(), input = new Input()
        runtime.$el = { dataset: { fffTextInputOptions: JSON.stringify({ editable: true, clearable: true }) } }; runtime.$refs = { input }
        await runtime.init(); const generation = runtime.runtimeDiagnostics.generation
        runtime.destroy(); runtime.$el.dataset.fffTextInputOptions = JSON.stringify({ editable: true, clearable: false })
        await runtime.refreshOptions()
        assert.equal(runtime.runtimeDiagnostics.generation, generation + 1)
        assert.equal(runtime.runtimeDiagnostics.bindingsDisposed, true)
    } finally { globalThis.document = oldDocument; globalThis.MutationObserver = oldObserver }
})


test('failed initialization retires bindings and can retry after corrected configuration', async () => {
    const oldDocument = globalThis.document, oldObserver = globalThis.MutationObserver
    globalThis.document = new EventTarget()
    let disconnected = 0
    globalThis.MutationObserver = class { observe() {} disconnect() { disconnected++ } }
    const owner = createTextInputRuntime(), input = new Input()
    owner.$refs = {input}; owner.$el = {dataset:{fffTextInputOptions:'invalid json'}}
    try {
        await assert.rejects(owner.init(), SyntaxError)
        assert.equal(disconnected, 1)
        owner.$el.dataset.fffTextInputOptions = JSON.stringify({editable:true, messages:{}, caretOnFocus:'preserve'})
        await owner.init()
        assert.equal(owner.commitToolValue('retry succeeded'), true)
        assert.equal(input.value, 'retry succeeded')
    } finally { owner.destroy(); globalThis.document = oldDocument; globalThis.MutationObserver = oldObserver }
})

test('tool undo respects an input type that no longer supports text selection', async () => {
    const oldDocument = globalThis.document, oldObserver = globalThis.MutationObserver
    globalThis.document = new EventTarget()
    globalThis.MutationObserver = class { observe() {} disconnect() {} }
    const owner = createTextInputRuntime(), input = new Input()
    owner.$refs = {input}; owner.$el = {dataset:{fffTextInputOptions:JSON.stringify({editable:true, messages:{}, caretOnFocus:'preserve'})}}
    try {
        await owner.init(); owner.commitToolValue('new value')
        input.type = 'email'; input.setSelectionRange = () => { throw new Error('Unsupported selection') }
        assert.equal(owner.undoToolEdit(), true)
        assert.equal(input.value, '')
    } finally { owner.destroy(); globalThis.document = oldDocument; globalThis.MutationObserver = oldObserver }
})

test('verification remains single-flight and ignores obsolete values and disposed owners', async () => {
    const { createInputVerification } = await import('../../resources/js/components/text-input/verification.js')
    const input = new Input(); input.value = 'first'
    const flights = []
    const owner = {$refs:{input}, options:{editable:true,verificationEnabled:true,verificationMinLength:1,verificationDebounce:0,messages:{}},
        $wire:{callSchemaComponentMethod:(_key,_method,{value})=>new Promise(resolve=>flights.push({value,resolve}))}}
    const verification = createInputVerification(owner)
    const tick = () => new Promise(resolve=>setTimeout(resolve,5))
    try {
        verification.schedule(); await tick(); assert.equal(flights.length,1)
        input.value = 'second'; verification.schedule(); await tick(); assert.equal(flights.length,1)
        flights[0].resolve({state:'success',message:'old'}); await tick()
        assert.equal(flights.length,2); assert.equal(flights[1].value,'second'); assert.notEqual(owner.verificationMessage,'old')
        flights[1].resolve({state:'success',message:'current'}); await tick(); assert.equal(owner.verificationMessage,'current')
        input.value = 'third'; verification.schedule(); await tick(); verification.dispose()
        flights[2].resolve({state:'success',message:'detached'}); await tick()
        assert.equal(owner.verificationState,'idle'); assert.equal(owner.verificationMessage,'')
    } finally { verification.dispose() }
})

test('interim dictation feedback retires at session end and selection restoration remains type-safe', () => {
    const oldWindow = globalThis.window, oldNavigator = Object.getOwnPropertyDescriptor(globalThis,'navigator')
    let recognition
    class Recognition { constructor(){recognition=this} start(){} stop(){this.onend?.()} abort(){} }
    globalThis.window = {SpeechRecognition:Recognition}
    Object.defineProperty(globalThis,'navigator',{value:{language:'en'},configurable:true})
    const input = new Input(); input.value = 'base'
    const owner = {$refs:{input},$el:{isConnected:true},options:{editable:true,speechDictation:true,speechDictationInterim:true,speechDictationMode:'replace-selection',messages:{}},showStatus(){}}
    const scope = createResourceScope()
    try {
        const dictation = createDictation(owner,scope); dictation.start()
        recognition.onresult({resultIndex:0,results:[Object.assign([{transcript:'interim'}],{isFinal:false})]})
        assert.equal(owner.status,'interim')
        recognition.onresult({resultIndex:0,results:[Object.assign([{transcript:'final'}],{isFinal:true})]})
        input.type = 'email'; input.setSelectionRange=()=>{throw Error('unsupported')}
        dictation.stop(); assert.equal(input.value,'basefinal'); assert.equal(owner.status,'')
    } finally {scope.dispose();globalThis.window=oldWindow;if(oldNavigator)Object.defineProperty(globalThis,'navigator',oldNavigator);else delete globalThis.navigator}
})


test('rapid emoji toggles cancel an import intent without mounting duplicate surfaces', async () => {
    const oldDocument = globalThis.document, oldObserver = globalThis.MutationObserver
    globalThis.document = new EventTarget(); globalThis.MutationObserver = class {observe(){} disconnect(){}}
    let resolveImport, active = null, opens = 0
    const module = {sharedEmojiPicker:{isOpenFor:owner=>active===owner,close(){active=null},disposeFor(owner){if(active===owner)active=null},async open({context}){opens++;active=context}}}
    const runtime = createTextInputRuntime({importEmoji:()=>new Promise(resolve=>{resolveImport=resolve})})
    runtime.$refs={input:new Input(),emojiTrigger:{}};runtime.$el={dataset:{fffTextInputOptions:JSON.stringify({editable:true,emojiPicker:true,messages:{},caretOnFocus:'preserve'})}}
    try {
        await runtime.init()
        const first = runtime.toggleEmojiPicker(); await runtime.toggleEmojiPicker()
        resolveImport(module); await first
        assert.equal(opens,0);assert.equal(runtime.emojiPickerOpen,false)
        await runtime.toggleEmojiPicker();assert.equal(opens,1)
        await runtime.toggleEmojiPicker();assert.equal(active,null);assert.equal(runtime.emojiPickerOpen,false)
        await runtime.toggleEmojiPicker();assert.equal(opens,2)
        runtime.destroy();assert.equal(active,null)
    } finally {runtime.destroy();globalThis.document=oldDocument;globalThis.MutationObserver=oldObserver}
})

test('an edit during lazy verification initialization is scheduled after the adapter becomes ready', async () => {
    const oldDocument = globalThis.document, oldObserver = globalThis.MutationObserver
    globalThis.document = new EventTarget();globalThis.MutationObserver=class {observe(){} disconnect(){}}
    const input = new Input(), requests=[]
    input.ownerDocument = {activeElement:input}
    const runtime=createTextInputRuntime();runtime.$refs={input};runtime.$el={dataset:{fffTextInputOptions:JSON.stringify({editable:true,verificationEnabled:true,verificationMinLength:1,verificationDebounce:0,messages:{},caretOnFocus:'preserve'})}}
    runtime.$wire={callSchemaComponentMethod:async(_key,_method,{value})=>{requests.push(value);return {state:'success',message:value}}}
    try {
        const initialization=runtime.init();input.value='edited before ready';input.dispatchEvent(new Event('input'))
        await initialization;await new Promise(resolve=>setTimeout(resolve,10))
        assert.deepEqual(requests,['edited before ready']);assert.equal(runtime.verificationState,'success')
    } finally {runtime.destroy();globalThis.document=oldDocument;globalThis.MutationObserver=oldObserver}
})

test('password segments retain an active weak segment and all four strength thresholds', async () => {
    const { passwordStrength } = await import('../../resources/js/components/text-input/counters.js')
    const labels = ['Very weak','Weak','Fair','Good','Strong']
    for (const [value, percent, label] of [['',0,''],['a',25,'Very weak'],['abcdefgh',25,'Weak'],['abcdefghijkl',50,'Fair'],['Abcdefghijk1',75,'Good'],['Fern!82Violet#47',100,'Strong']]) {
        assert.deepEqual(passwordStrength(value,labels),{score:labels.indexOf(label)<0?0:labels.indexOf(label),percent,label})
    }
    const owner = createTextInputRuntime(); owner.options = { passwordStrengthLabels:labels }
    owner.value = 'Abcdefghijk1'; const original = owner.passwordStrengthMeta
    assert.equal(owner.passwordStrengthMeta,original)
    owner.value = 'a'; assert.equal(owner.passwordStrengthMeta.percent,25)
    owner.options = { passwordStrengthLabels:['Too short','Weak','Fair','Good','Strong'] }
    assert.equal(owner.passwordStrengthMeta.label,'Too short')
})

test('password checklist memoization updates on value and policy changes without retaining old rule states', () => {
    const owner = createTextInputRuntime()
    owner.options = {passwordRequirements:{min:12,uppercase:1,numbers:2}}
    owner.value = 'Short47'
    const initial = owner.passwordRequirementChecks
    assert.deepEqual(initial,{min:false,uppercase:true,numbers:true})
    assert.equal(owner.passwordRequirementChecks,initial)
    owner.value = '';assert.deepEqual(owner.passwordRequirementChecks,{min:false,uppercase:false,numbers:false})
    owner.options = {passwordRequirements:{min:1}}
    owner.value = 'é';assert.deepEqual(owner.passwordRequirementChecks,{min:true})
})
