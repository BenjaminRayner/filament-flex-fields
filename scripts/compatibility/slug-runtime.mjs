import assert from 'node:assert/strict'
import { createSlugRuntime } from '../../resources/js/slug/runtime.js'
import { createSegmentRuntime } from '../../resources/js/components/segments/runtime.js'
import { fromEditableSlug, slugify, normalizeSlug, buildPermalink, needsServerTransliteration } from '../../resources/js/slug/value.js'

// Behavior, not source-string assertions. No browser or existing test files are modified.
const tasks = []
const wire = () => {
    const values = new Map([['form.title', 'Łódź & Friends'], ['form.slug', 'initial-slug']])
    const writes = [], watchers = new Map(), requests = []
    return {
        values, writes, watchers, requests,
        $get(path) { return values.get(path) },
        $set(path, value, live) {
            assert.equal(live, false, 'Editor writes must never request a full render')
            writes.push({ path, value }); values.set(path, value)
            for (const watch of watchers.get(path) ?? []) watch(value)
            return Promise.resolve()
        },
        $watch(path, callback) {
            if (!watchers.has(path)) watchers.set(path, new Set())
            watchers.get(path).add(callback)
            return () => watchers.get(path).delete(callback)
        },
        callSchemaComponentMethod(key, method, params) {
            let resolve, reject
            const promise = new Promise((yes, no) => { resolve = yes; reject = no })
            requests.push({ key, method, params, resolve, reject })
            return promise
        },
    }
}
function mount(options = {}, connection = wire()) {
    const classes = new Set()
    const input = { value: 'initial-slug', focus() {}, setSelectionRange(a, b) { this.selection = [a, b] }, classList: { add() {} } }
    const owner = createSlugRuntime({
        initialSlug: 'initial-slug', statePath: 'form.slug', sourcePath: 'form.title',
        inlineEditPendingPath: 'form.slug_pending', autoUpdateDisabledPath: 'form.slug_custom',
        separator: '-', inlineEditing: true, sourceLive: true, autoGenerate: true, debounceMs: 0,
        showRegenerateButton: true, showCopyButton: true, componentKey: 'slug',
        labels: { error: 'Request failed', copyError: 'Copy failed' }, ...options,
    })
    Object.assign(owner, {
        $wire: connection, $refs: { slugInput: input }, $dispatch() {},
        $el: { classList: { add(...names) { names.forEach(name => classes.add(name)) }, toggle() {} }, querySelector() { return {} } },
        $nextTick(callback) { tasks.push(callback) },
    })
    owner.init(); return { owner, input, connection }
}
const tick = async () => { while (tasks.length) tasks.shift()(); await Promise.resolve() }
let scenarios = 0
function scenario(name, callback) { callback(); scenarios++; console.log(`PASS: ${name}`) }
scenario('homepage, literal multi-character separators and Latin generation', () => {
    assert.equal(fromEditableSlug('', '--', { allowHomepage: true }), '/')
    assert.equal(slugify('Żółć & Friends', '-', 12), 'zolc-friends')
    assert.equal(slugify('Hello World', '--'), 'hello--world')
    assert.equal(normalizeSlug('Hello---World', '--'), 'hello--world')
    assert.equal(needsServerTransliteration('Łódź'), false)
    assert.equal(needsServerTransliteration('東京'), true)
    assert.equal(buildPermalink('/', { urlHost: 'https://example.test', allowHomepage: true }), 'https://example.test')
    assert.equal(buildPermalink('hello', { urlHost: 'https://example.test', urlPath: '/posts/', selfHealingPermalink: true, permalinkRecordKey: 42, selfHealingSeparator: '-' }), 'https://example.test/posts/hello-42')
})
const plain = mount()
scenario('idempotent initialization registers each watcher once', () => {
    plain.owner.init()
    assert.equal([...plain.connection.watchers.values()].reduce((sum, group) => sum + group.size, 0), 3)
})
scenario('Edit and Cancel are local transactions, preserve the committed value', () => {
    plain.owner.startEditing(); assert.equal(plain.owner.mode, 'edit')
    plain.owner.onInput({ target: { value: 'unsaved' } })
    assert.equal(plain.owner.slug, 'initial-slug')
    plain.owner.cancelEditing()
    assert.equal(plain.owner.slug, 'initial-slug'); assert.equal(plain.owner.draftSlug, 'initial-slug')
    assert.equal(plain.connection.requests.length, 0)
    assert.equal(plain.connection.values.get('form.slug_pending'), false)
})
const validation = mount({ validateOnConfirm: true })
validation.owner.startEditing(); validation.owner.onInput({ target: { value: 'new-slug' } })
let confirm = validation.owner.confirmEditing()
scenario('confirmation sends one renderless validation command', () => {
    assert.equal(validation.connection.requests.length, 1)
    assert.equal(validation.connection.requests[0].method, 'validateSlugDraft')
    validation.owner.confirmEditing(); assert.equal(validation.connection.requests.length, 1)
})
validation.owner.cancelEditing(); validation.connection.requests[0].resolve({ valid: true, slug: 'new-slug', url: null }); await confirm
scenario('late confirmation cannot commit after Cancel', () => assert.equal(validation.owner.slug, 'initial-slug'))
validation.owner.startEditing(); validation.owner.onInput({ target: { value: 'one' } }); confirm = validation.owner.confirmEditing()
validation.owner.onInput({ target: { value: 'two' } }); validation.connection.requests[1].resolve({ valid: true, slug: 'one' }); await confirm
scenario('editing the draft invalidates an in-flight confirmation', () => {
    assert.equal(validation.owner.slug, 'initial-slug'); assert.equal(validation.owner.draftSlug, 'two')
})
confirm = validation.owner.confirmEditing(); validation.connection.requests[2].reject(new Error('Network failed')); await confirm
scenario('failed validation keeps the draft and does not fail open', () => {
    assert.equal(validation.owner.mode, 'edit'); assert.equal(validation.owner.uniqueError, 'Request failed'); assert.equal(validation.owner.slug, 'initial-slug')
})
confirm = validation.owner.confirmEditing(); validation.connection.requests[3].resolve({ valid: false, slug: 'two', message: 'Taken' }); await confirm
scenario('unavailable slug reports an error without committing', () => assert.equal(validation.owner.uniqueError, 'Taken'))
confirm = validation.owner.confirmEditing(); validation.connection.requests[4].resolve({ valid: true, slug: 'two', url: 'https://example.test/two' }); await confirm
scenario('successful confirmation commits and resolves the current custom URL', () => {
    assert.equal(validation.owner.slug, 'two'); assert.equal(validation.owner.mode, 'view'); assert.equal(validation.owner.fullUrl(), 'https://example.test/two')
    assert.equal(validation.connection.values.get('form.slug_custom'), true)
})
const generation = mount({ serverGenerate: true })
let first = generation.owner.generate('one'), second = generation.owner.generate('two')
generation.connection.requests[1].resolve('second'); await second
generation.connection.requests[0].resolve('first'); await first
scenario('only the newest generation response can write state', () => assert.equal(generation.owner.slug, 'second'))
first = generation.owner.generate('three'); generation.owner.startEditing(); generation.connection.requests[2].resolve('third'); await first
scenario('entering Edit invalidates in-flight generation', () => assert.equal(generation.owner.slug, 'second'))
generation.owner.cancelEditing(); first = generation.owner.generate('four'); generation.connection.requests[3].reject(new Error('Model generator failed')); await first
scenario('a server generator failure never falls back to a different client algorithm', () => {
    assert.equal(generation.owner.slug, 'second'); assert.equal(generation.owner.uniqueError, 'Request failed')
})
const sharedWire = wire(), a = mount({}, sharedWire), b = mount({}, sharedWire)
a.owner.startEditing(); b.owner.startEditing(); a.owner.cancelEditing()
scenario('closing one same-path editor preserves another pending editor', () => assert.equal(sharedWire.values.get('form.slug_pending'), true))
b.owner.cancelEditing(); a.owner.startEditing(); b.owner.startEditing(); a.owner.destroy()
scenario('pending ownership can be reused after an earlier transaction', () => assert.equal(sharedWire.values.get('form.slug_pending'), true))
b.owner.destroy()
scenario('destroy clears the last pending edit and unsubscribes all watchers', () => {
    assert.equal(sharedWire.values.get('form.slug_pending'), false)
    assert.equal([...sharedWire.watchers.values()].reduce((sum, group) => sum + group.size, 0), 0)
})
const dead = mount({ serverGenerate: true }); first = dead.owner.generate('late'); dead.owner.destroy(); dead.connection.requests[0].resolve('late'); await first
scenario('a destroyed owner cannot publish an async result', () => assert.equal(dead.owner.slug, 'initial-slug'))
const direct = mount({ inlineEditing: false }); direct.owner.onInput({ target: { value: 'typed-' } })
scenario('direct input keeps the native draft/caret value while committing normalized state', () => {
    assert.equal(direct.owner.draftSlug, 'typed-'); assert.equal(direct.owner.slug, 'typed'); direct.owner.onBlur(); assert.equal(direct.owner.draftSlug, 'typed')
})
let dispatched = 0
const button = { dataset: { segmentValue: 'edit' }, disabled: false, hidden: false, style: {} }
const commands = createSegmentRuntime({ property: 'command', commands: true, onCommand() { dispatched++ } })
Object.assign(commands, { $refs: { track: { querySelectorAll: () => [button], closest: () => ({ classList: { contains: () => false } }) } } })
commands.select('edit'); commands.select('edit')
scenario('shared SegmentControl command buttons are reusable commands, not selected radios', () => { assert.equal(dispatched, 2); assert.equal(commands.isSelected('edit'), false) })
plain.owner.destroy(); validation.owner.destroy(); generation.owner.destroy(); direct.owner.destroy(); await tick()
console.log(`PASS: ${scenarios} Slug/command lifecycle scenarios.`)
