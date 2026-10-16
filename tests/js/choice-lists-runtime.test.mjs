import test from 'node:test'
import assert from 'node:assert/strict'
import { createChoiceListRuntime } from '../../resources/js/choice-lists/runtime.js'
import { createChoiceSelection, choiceIdentity } from '../../resources/js/choice-lists/selection.js'
import { choiceDestination } from '../../resources/js/choice-lists/keyboard.js'

function fixture(multiple = true, initial = multiple ? ['a'] : 'a') {
    const old = { window: globalThis.window, Alpine: globalThis.Alpine, requestAnimationFrame: globalThis.requestAnimationFrame, cancelAnimationFrame: globalThis.cancelAnimationFrame }
    const frames = new Map(), watches = new Set(), observers = new Set(), listeners = new Map()
    let sequence = 0
    globalThis.window = { FffAssetInjector: { isElementActive: () => true } }
    globalThis.Alpine = { watch(getter, callback) { const value = { getter, callback }; watches.add(value); return () => watches.delete(value) } }
    globalThis.requestAnimationFrame = fn => { frames.set(++sequence, fn); return sequence }
    globalThis.cancelAnimationFrame = id => frames.delete(id)
    const classes = initial => { const values = new Set(initial); return { contains: value => values.has(value), add: value => values.add(value), toggle(value, enabled) { if (enabled) values.add(value); else values.delete(value) } } }
    let focus = null, rtl = false, writes = 0
    const node = (key, checked = false, blocked = false) => {
        const attrs = new Map([['aria-disabled', String(blocked)], ['aria-checked', String(checked)]])
        const input = { checked, disabled: blocked }, indicator = { setAttribute() { writes++ } }
        const element = {
            dataset: { choiceValue: key }, classList: classes(checked ? ['is-selected'] : []),
            tabIndex: -1, getAttribute: key => attrs.get(key) ?? null,
            setAttribute(key, value) { writes++; attrs.set(key, value) },
            removeAttribute(key) { if (key === 'data-choice-motion') delete this.dataset.choiceMotion; else attrs.delete(key) },
            querySelector: selector => selector === 'input' ? input : indicator,
            focus() { focus = key },
        }
        return element
    }
    let nodes = [node('a', true), node('b'), node('locked', false, true), node('c')]
    const rootAttrs = new Map([['aria-disabled', 'false']])
    const root = {
        dataset: { choiceConfig: JSON.stringify({ options: [{value:'a'}, {value:'b'}, {value:'locked',disabled:true}, {value:'c'}], maxSelections: 2 }) },
        classList: classes([]), isConnected: true,
        querySelectorAll: () => nodes, contains: el => nodes.includes(el), matches: () => rtl,
        getAttribute: key => rootAttrs.get(key) ?? null, setAttribute(key, value) { rootAttrs.set(key, value) },
        addEventListener(name, fn) { listeners.set(name, fn) }, removeEventListener(name, fn) { if(listeners.get(name) === fn) listeners.delete(name) },
        ownerDocument: { defaultView: { MutationObserver: class { constructor(fn) { this.fn=fn; observers.add(this) } observe() {} disconnect() { observers.delete(this) } } } },
    }
    const runtime = createChoiceListRuntime({ state: initial }, multiple)
    runtime.$el = root
    const errors = []
    runtime.$dispatch = (name, detail) => errors.push({name,detail})
    const emit = (type, key, properties = {}) => {
        const element = nodes.find(el => el.dataset.choiceValue === key)
        const event = { target: { closest: () => element }, key: properties.key, preventDefault() { this.prevented=true }, ...properties }
        listeners.get(type)?.(event)
        return event
    }
    return { root, runtime, frames, watches, observers, listeners, errors, emit,
        get focus() { return focus }, get writes() { return writes }, get nodes() { return nodes },
        set rtl(value) { rtl=value },
        external(state) { runtime.state=state; for(const watch of watches)watch.callback() },
        replace() { nodes=[node('new')]; root.dataset.choiceConfig=JSON.stringify({ options:[{value:'new'}] }); for(const observer of observers)observer.fn([{type:'childList',target:root}]); const jobs=[...frames.values()];frames.clear();for(const fn of jobs)fn() },
        restore() { runtime.destroy(); Object.assign(globalThis,old) },
    }
}

test('selection identities are strict, numeric-safe and prototype-safe', () => {
    for (const value of [null, true, {}, [], 1.2, NaN]) assert.equal(choiceIdentity(value), null)
    const model=createChoiceSelection({multiple:true,options:[{value:'0'},{value:'001'},{value:'__proto__'}]})
    assert.deepEqual(model.change(0, []), ['0'])
    assert.deepEqual(model.change('__proto__', ['001']), ['001','__proto__'])
    assert.deepEqual(model.change('missing', ['001']), ['001'])
})

test('maximum blocks additions while allowing deselection; locked and globally disabled stay unchanged', () => {
    const model=createChoiceSelection({multiple:true,maxSelections:1,options:[{value:'a'},{value:'b'},{value:'locked',disabled:true}]})
    assert.equal(model.canChange('b',['a']), false)
    assert.deepEqual(model.change('a',['a']), [])
    assert.deepEqual(model.change('locked',[]), [])
    assert.deepEqual(model.change('a',[],true), [])
})

test('keyboard destinations wrap and respect horizontal RTL', () => {
    assert.equal(choiceDestination(['a','b','c'],'a','ArrowUp'), 'c')
    assert.equal(choiceDestination(['a','b','c'],'b','ArrowRight',true), 'a')
    assert.equal(choiceDestination(['a','b','c'],'b','End'), 'c')
    assert.equal(choiceDestination([],'a','Home'), null)
})

test('initialization is idempotent and unchanged render does not write attributes', async () => {
    const f=fixture()
    try { const ready=f.runtime.init();assert.equal(f.runtime.init(),ready);await ready;assert.equal(f.watches.size,2);assert.equal(f.listeners.size,2);assert.equal(f.frames.size,0);const writes=f.writes;f.runtime.render();assert.equal(f.writes,writes);assert.deepEqual(f.errors,[]) }
    finally { f.restore() }
})

test('SVG-origin click toggles once, updates indicator and enforces maximum immediately', async () => {
    const f=fixture()
    try { await f.runtime.init();f.emit('click','b');assert.deepEqual(f.runtime.state,['a','b']);assert.equal(f.nodes[1].getAttribute('aria-checked'),'true');f.emit('click','c');assert.deepEqual(f.runtime.state,['a','b']);f.emit('click','a');assert.deepEqual(f.runtime.state,['b']);f.emit('click','c');assert.deepEqual(f.runtime.state,['b','c']);assert.deepEqual(f.errors,[]) }
    finally { f.restore() }
})

test('checklist arrows move focus without changing state and repeated Space does not toggle', async () => {
    const f=fixture()
    try { await f.runtime.init();f.emit('keydown','a',{key:'ArrowDown'});assert.equal(f.focus,'b');assert.deepEqual(f.runtime.state,['a']);f.emit('keydown','b',{key:' ',repeat:true});assert.deepEqual(f.runtime.state,['a']);f.emit('keydown','b',{key:' '});assert.deepEqual(f.runtime.state,['a','b']) }
    finally { f.restore() }
})

test('radio arrows select, skip locks and keep independent owner state', async () => {
    const f=fixture(false)
    try { await f.runtime.init();f.emit('keydown','b',{key:'ArrowDown'});assert.equal(f.runtime.state,'c');assert.equal(f.focus,'c');f.rtl=true;f.emit('keydown','c',{key:'ArrowRight'});assert.equal(f.runtime.state,'b');f.external(null);assert.ok(f.nodes.every(el=>el.getAttribute('aria-checked')==='false')) }
    finally { f.restore() }
})

test('radio hydration stays still and only changed selections receive a retired motion marker', async () => {
    const f = fixture(false)
    try {
        await f.runtime.init()
        assert.ok(f.nodes.every(node => node.dataset.choiceMotion === undefined))
        f.runtime.change('a')
        assert.ok(f.nodes.every(node => node.dataset.choiceMotion === undefined))
        f.runtime.change('b')
        assert.equal(f.nodes[0].dataset.choiceMotion, 'cleared')
        assert.equal(f.nodes[1].dataset.choiceMotion, 'selected')
        f.emit('animationend', 'b', { animationName: 'unrelated' })
        assert.equal(f.nodes[1].dataset.choiceMotion, 'selected')
        f.emit('animationend', 'b', { animationName: 'fff-flex-radiolist-indicator-pop' })
        assert.equal(f.nodes[1].dataset.choiceMotion, undefined)
        assert.deepEqual(f.errors, [])
    } finally { f.restore() }
})

test('server morph reindexes current nodes and disposal retires watchers, listeners and observer', async () => {
    const f=fixture()
    try { await f.runtime.init();f.replace();f.emit('click','new');assert.deepEqual(f.runtime.state,['new']);assert.equal(f.nodes[0].getAttribute('aria-checked'),'true');f.runtime.destroy();f.runtime.destroy();assert.equal(f.watches.size,0);assert.equal(f.listeners.size,0);assert.equal(f.observers.size,0);assert.equal(f.frames.size,0);f.runtime.change('new');assert.deepEqual(f.runtime.state,['new']);assert.deepEqual(f.errors,[]) }
    finally { f.restore() }
})

test('destroy before activation continuation creates no listeners or watchers', async () => {
    const f=fixture()
    try { const ready=f.runtime.init();f.runtime.destroy();await ready;assert.equal(f.listeners.size,0);assert.equal(f.watches.size,0);assert.equal(f.observers.size,0) }
    finally { f.restore() }
})
