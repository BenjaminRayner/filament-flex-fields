import assert from 'node:assert/strict'
import { createTextInputRuntime } from '../../resources/js/components/text-input/runtime.js'
import { boundInputValue, normalizeText, insertInputText } from '../../resources/js/components/text-input/editing.js'
import { countText, passwordStrength, passwordChecks, counterLabel } from '../../resources/js/components/text-input/counters.js'
import { createInputVerification } from '../../resources/js/components/text-input/verification.js'
import { createInputDraft } from '../../resources/js/components/text-input/drafts.js'
import { createResourceScope } from '../../resources/js/runtime/lifecycle/resource-scope.js'
import { guardDemoFocus } from '../../resources/js/catalog/demo-focus.js'
import { moveInputCaret } from '../../resources/js/components/text-input/focus.js'

class Input extends EventTarget {
    value = ''; maxLength = -1; type = 'text'; disabled = false; readOnly = false; selectionStart = 0; selectionEnd = 0
    ownerDocument = globalThis.document
    focus() { this.ownerDocument.activeElement = this } select() { this.setSelectionRange(0, this.value.length) }
    setSelectionRange(start, end) { this.selectionStart = start; this.selectionEnd = end }
}
let checks = 0
const check = (value, expected) => { assert.deepEqual(value, expected); checks++ }
globalThis.document = new EventTarget()
globalThis.MutationObserver = class { observe() {} disconnect() {} }
const input = new Input()
const options = { editable: true, caretOnFocus: 'preserve', pasteMode: 'preserve', normalizeOnBlur: 'none', unicodeNormalization: 'none', counterMode: 'characters', counterDisplay: 'used', counterWarningAt: .85, clearable: true, clearValue: '', messages: { copy_error: 'Error', speech_error: 'Error', draft_restored: 'Restored', draft_error: 'Unavailable' } }
const owner = createTextInputRuntime(); owner.$refs = { input }; owner.$el = { isConnected: true, dataset: { fffTextInputOptions: JSON.stringify(options) } }
input.value = '👨‍👩‍👧‍👦'; await owner.init()
const initialGeneration = owner.runtimeDiagnostics.generation
owner.$el.dataset.fffTextInputOptions = JSON.stringify({ ...options, initialCount: 7 })
await owner.refreshOptions()
check(owner.runtimeDiagnostics.generation, initialGeneration)
check(owner.runtimeDiagnostics.bindingsDisposed, false)
check(countText(input.value, 'graphemes'), 1)
check(boundInputValue(input, '👨‍👩‍👧‍👦xy', { counterMode: 'graphemes', enforceCounterLimit: true, counterLimit: 2 }), '👨‍👩‍👧‍👦x')
check(boundInputValue(input, 'é😀x', { counterMode: 'bytes', enforceCounterLimit: true, counterLimit: 6 }), 'é😀')
for (const [value, mode, limit, expected] of [
    ['a😀b', 'characters', 2, 'a😀'], ['one  two three', 'words', 2, 'one  two '],
    ['é😀x', 'bytes', 5, 'é'], ['e\u0301👨‍👩‍👧‍👦x', 'graphemes', 2, 'e\u0301👨‍👩‍👧‍👦'],
]) check(boundInputValue(input, value, { counterMode: mode, enforceCounterLimit: true, counterLimit: limit }), expected)
input.maxLength = 2
check(boundInputValue(input, 'a😀b'), 'a')
input.maxLength = -1
const largeStart = performance.now()
check(boundInputValue(input, 'a'.repeat(100000), { counterMode: 'characters', enforceCounterLimit: true, counterLimit: 50000 }).length, 50000)
console.log(`100k-character paste bounded to 50k: ${(performance.now() - largeStart).toFixed(1)} ms`)
check(normalizeText('  a\n  b  ', { normalizeOnBlur: 'collapse-whitespace', unicodeNormalization: 'none' }), 'a b')
check(normalizeText('e\u0301', { normalizeOnBlur: 'none', unicodeNormalization: 'NFC' }), 'é')
check(passwordStrength('aaaaaaaaaaaa1!', ['0','1','2','3','4']).score, 1)
check(passwordChecks('ÉAb9!', { min: 5, uppercase: 2, lowercase: 1, numbers: 1, symbols: 1 }), { min: true, uppercase: true, lowercase: true, numbers: true, symbols: true })
check(counterLabel(1000, 2000, 'both', null, 'en-US'), '1,000/2,000 · 1,000')
const originalSegmenter = Intl.Segmenter
Intl.Segmenter = undefined
assert.throws(() => countText(input.value, 'graphemes')); checks++
Intl.Segmenter = originalSegmenter
owner.clearInput(); check(input.value, ''); check(owner.shouldShowActionGroup, false)
owner.undoToolEdit(); check(input.value, '👨‍👩‍👧‍👦')
input.disabled = true; await owner.refreshOptions(); check(owner.options.editable, false)
check(owner.commitToolValue('unauthorized'), false)
input.disabled = false; await owner.refreshOptions(); check(owner.options.editable, true)
input.value = 'مرحبا'; moveInputCaret(input, 'end'); check(input.selectionStart, input.value.length)
moveInputCaret(input, 'start'); check(input.selectionStart, 0)
const nativeEmail = { type: 'email', value: 'demo@example.test', setSelectionRange() { throw new Error('Email rejects selection ranges') } }
const moves = []
nativeEmail.ownerDocument = { activeElement: nativeEmail, defaultView: { getSelection: () => ({ modify: (...args) => moves.push(args) }) } }
check(moveInputCaret(nativeEmail, 'end'), true)
check(moves, [['move', 'forward', 'lineboundary']])
check(nativeEmail.type, 'email'); check(nativeEmail.value, 'demo@example.test')
nativeEmail.type = 'number'; check(moveInputCaret(nativeEmail, 'end'), true)
check(nativeEmail.type, 'number')
nativeEmail.type = 'date'; check(moveInputCaret(nativeEmail, 'end'), false)
input.value = 'draft'; input.setSelectionRange(1, 3); insertInputText(input, 'X'); check(input.value, 'dXft')
// First pointer focus obeys the explicit policy; later native placement/drag
// remains editable instead of continually forcing a caret to an endpoint.
owner.$el.dataset.fffTextInputOptions = JSON.stringify({ ...options, caretOnFocus: 'end' })
await owner.refreshOptions()
document.activeElement = null
input.dispatchEvent(new Event('pointerdown')); input.focus(); input.dispatchEvent(new Event('focus'))
input.setSelectionRange(1, 1); input.dispatchEvent(new Event('pointerup'))
check(input.selectionStart, input.value.length)
input.dispatchEvent(new Event('pointerdown')); input.setSelectionRange(1, 3); input.dispatchEvent(new Event('pointerup'))
check([input.selectionStart, input.selectionEnd], [1, 3])
owner.destroy(); input.value = 'detached'; input.dispatchEvent(new Event('input')); check(owner.value, 'dXft')

// Latest-value verification serializes work and ignores late/disposed responses.
let finish, calls = []
const verifierOwner = { options: { editable: true, verificationEnabled: true, verificationMinLength: 1, verificationDebounce: 0, messages: { verification_pending: 'Checking', verification_error: 'Error' } }, $refs: { input },
    $wire: { callSchemaComponentMethod(_key, method, payload) { calls.push({ method, payload }); return new Promise(resolve => { finish = resolve }) } } }
input.value = 'first'; const verifier = createInputVerification(verifierOwner)
const tick = () => new Promise(resolve => setTimeout(resolve, 5))
verifier.schedule(); await tick(); input.value = 'second'; verifier.schedule(); await tick()
check(calls.length, 1); finish({ state: 'success', message: 'first result' }); await tick()
check(calls.length, 2); check(verifierOwner.verificationMessage, 'Checking')
verifier.dispose(); finish({ state: 'success', message: 'late result' }); await tick(); check(verifierOwner.verificationState, 'idle')

// Drafts remain opt-in, expire and never overwrite a filled native field.
const values = new Map()
globalThis.location = { pathname: '/form' }
globalThis.sessionStorage = { getItem: key => values.get(key) ?? null, setItem: (key, value) => values.set(key, value), removeItem: key => values.delete(key) }
const draftOwner = { $refs: { input }, $el: { isConnected: true }, options: { editable: true, draftKey: 'probe', draftStorage: 'session', draftLifetime: 60, messages: {} }, commitToolValue(value) { input.value = value }, showStatus() {} }
values.set('fff:draft:v1:/form:probe', JSON.stringify({ saved: Date.now(), value: 'saved' }))
input.value = ''; const draft = createInputDraft(draftOwner); check(input.value, 'saved'); draft.dispose()
input.value = 'server'; const filled = createInputDraft(draftOwner); check(input.value, 'server'); filled.dispose()
values.set('fff:draft:v1:/form:probe', JSON.stringify({ saved: Date.now() - 61000, value: 'old' }))
input.value = ''; const expired = createInputDraft(draftOwner); check(input.value, ''); expired.dispose()
// Catalog-only guard rejects automatic focus until real user intent, including
// focus already assigned by an extension before the optional module initialized.
const demoListeners = new Map()
const demoDoc = { activeElement: null,
    addEventListener(name, fn) { demoListeners.set(name, fn) },
    removeEventListener(name, fn) { if (demoListeners.get(name) === fn) demoListeners.delete(name) },
}
const demoInput = new Input(); demoInput.ownerDocument = demoDoc
let blurs = 0
demoInput.blur = () => { blurs++; demoDoc.activeElement = null }
demoDoc.activeElement = demoInput
const demoScope = createResourceScope(); guardDemoFocus(demoInput, demoScope)
check(blurs, 1)
demoListeners.get('pointerdown')({ isTrusted: false })
demoDoc.activeElement = demoInput; demoInput.dispatchEvent(new Event('focus')); check(blurs, 2)
demoListeners.get('keydown')({ isTrusted: true })
check(demoListeners.size, 0)
demoDoc.activeElement = demoInput; demoInput.dispatchEvent(new Event('focus')); check(blurs, 2)
demoScope.dispose()
const remountedScope = createResourceScope(); guardDemoFocus(demoInput, remountedScope); check(blurs, 2); remountedScope.dispose()
const autofocusScope = createResourceScope(); demoInput.autofocus = true; guardDemoFocus(demoInput, autofocusScope); check(blurs, 2); autofocusScope.dispose()
console.log(`PASS: ${checks} assertions for Unicode limits, transforms, password policy, capability revocation, tool undo, verification races and draft isolation.`)
