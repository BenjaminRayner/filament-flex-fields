import assert from 'node:assert/strict'
import { createFactoryRegistry } from '../../resources/js/runtime/alpine/factory-registry.js'
import { factoryIdentity, resolveModuleFactoryIdentity } from '../../resources/js/runtime/alpine/factory-identity.js'
import { getAlpineLoadGate, resetAlpineLoadGateForTests } from '../../resources/js/runtime/alpine/load-gate.js'
import { createDom, createAssetBatch } from '../../tests/js/helpers/flex-field-asset-injector-dom.mjs'

const a = () => ({ owner: 'a' }), b = () => ({ owner: 'b' })
const identity = (overrides = {}) => ({ package: 'vendor/component', component: 'vendor-field', export: 'default', abi: 1, module: 'https://panel.test/releases/a.js?v=one', ...overrides })
let checks = 0
const pass = name => { checks++; console.log(`PASS: ${name}`) }
{
    const factories = new Map(), owners = new Map(), registry = createFactoryRegistry({ factories, owners })
    registry.claim('shared', a, identity())
    for (const descriptor of [identity({ package: 'other/component' }), identity({ component: 'other-field' }), identity({ export: 'another' }), identity({ module: 'https://panel.test/releases/b.js?v=two' })]) {
        assert.throws(() => registry.claim('shared', b, descriptor), /conflict/)
        assert.equal(factories.get('shared'), a)
        assert.equal(owners.size, 1)
    }
    assert.throws(() => registry.claim('shared', b, identity()), /conflict/)
    assert.equal(factories.get('shared')().owner, 'a')
    registry.claim('shared', a, identity())
    pass('package/component/export/release conflicts and forged same identity preserve the existing source')
}
{
    const factories = new Map(), registry = createFactoryRegistry({ factories })
    let wraps = 0
    const decorate = source => { wraps++; return (...args) => ({ ...source(...args), ready: true }) }
    registry.claim('shared', a, identity())
    registry.decorate('shared', a, identity(), decorate)
    registry.decorate('shared', a, identity(), decorate)
    registry.claim('shared', a, identity())
    assert.equal(wraps, 1)
    assert.deepEqual(factories.get('shared')(), { owner: 'a', ready: true })
    assert.throws(() => registry.decorate('shared', b, identity(), decorate), /conflict/)
    assert.equal(wraps, 1)
    pass('same source registers/decorates idempotently; foreign sources cannot replace readiness')
}
{
    const factories = new Map(), registry = createFactoryRegistry({ factories })
    registry.claim('legacy', a)
    registry.claim('legacy', b)
    assert.equal(factories.get('legacy'), a)
    assert.throws(() => registry.decorate('legacy', b, identity(), source => source), /conflict/)
    registry.claim('legacy', a, identity())
    assert.throws(() => registry.claim('legacy', b), /conflict/)
    pass('legacy anonymous idempotence keeps the first source; only that exact source can acquire a typed identity')
}
{
    const owners = new Map(), factories = new Map(), registry = createFactoryRegistry({ owners, factories, limit: 2 })
    registry.claim('one', a, identity())
    registry.claim('two', b, identity({ component: 'vendor-second' }))
    registry.claim('one', a, identity())
    assert.throws(() => registry.claim('three', a, identity()), /budget/)
    assert.equal(owners.size, 2)
    assert.equal(factories.size, 2)
    pass('factory admission is bounded and warm idempotent claims consume no extra slots')
}
for (const name of ['', '123', 'bad()', 'shared\n', 'shared\r', '__proto__', 'prototype', 'constructor', 'a'.repeat(129)]) {
    const factories = new Map(), registry = createFactoryRegistry({ factories })
    assert.throws(() => registry.claim(name, a, identity()), /bounded/)
    assert.equal(factories.size, 0)
}
pass('invalid/prototype-sensitive Alpine aliases reject without mutation')
for (const descriptor of [identity({ abi: 2 }), identity({ abi: '1' }), identity({ package: '../vendor' }), identity({ package: 'vendor/component\n' }), identity({ component: 'vendor-field\n' }), identity({ export: 'default\n' }), identity({ component: 'invalid/name' }), identity({ export: '__proto__' }), identity({ module: 'javascript:alert(1)' }), identity({ extra: true }), Object.create(identity())]) {
    assert.throws(() => factoryIdentity(descriptor), /identity|ABI/)
}
assert.equal(Object.isFrozen(factoryIdentity(identity())), true)
pass('ABI/types/own fields/metadata shape/URL/export grammar are checked and the admitted identity is immutable')
for (const limit of [0, -1, 1.5, Infinity, '4096', 16385]) assert.throws(() => createFactoryRegistry({ limit }), /budget/)
{
    const factories = new Map([['legacy', a]])
    const registry = createFactoryRegistry({ factories, limit: 1 })
    assert.throws(() => registry.claim('newAlias', b, identity()), /budget/)
    registry.claim('legacy', a, identity())
    assert.equal(factories.size, 1)
}
{
    const { document, body } = createDom(), wrapper = document.createElement('section'), element = document.createElement('div')
    body.appendChild(wrapper)
    const batch = createAssetBatch([], [], { consumerComponent: 'composite-field' })
    wrapper.appendChild(batch); wrapper.appendChild(element)
    const url = 'https://panel.test/shared/select.js?v=one'
    const descriptor = { package: 'janczakb/filament-flex-fields', component: 'select-field', export: 'default', abi: 1 }
    batch.setAttribute('data-fff-factory-owners', JSON.stringify({ [url]: descriptor }))
    const owner = resolveModuleFactoryIdentity(element, 'composite-field', url, { default: a, fffFactoryIdentity: descriptor })
    assert.equal(owner.component, 'select-field')
    assert.equal(owner.package, descriptor.package)
    assert.throws(() => resolveModuleFactoryIdentity(element, 'composite-field', 'https://other.test/unowned.js', { default: a, fffFactoryIdentity: descriptor }), /not declared/)
    assert.throws(() => resolveModuleFactoryIdentity(element, 'composite-field', url, { default: a, fffFactoryIdentity: { ...descriptor, package: 'other/component' } }), /disagrees/)
    batch.setAttribute('data-fff-factory-owners', '{broken')
    assert.throws(() => resolveModuleFactoryIdentity(element, 'composite-field', url, { default: a }), /metadata/)
    batch.setAttribute('data-fff-factory-owners', 'x'.repeat(131073))
    assert.throws(() => resolveModuleFactoryIdentity(element, 'composite-field', url, { default: a }), /budget/)
    pass('SSR module owner governs a composed child; module disagreement and malformed/oversized owner maps reject')
}
{
    resetAlpineLoadGateForTests()
    const callbacks = new Map(), registrations = []
    globalThis.Alpine = { data(name, callback) { registrations.push(name); callbacks.set(name, callback) } }
    const gate = getAlpineLoadGate()
    gate.registerAlpineData('globalAlias', a, identity())
    gate.prepareAlpineData('globalAlias', a, identity())
    gate.prepareAlpineData('globalAlias', a, identity())
    assert.throws(() => gate.registerAlpineData('globalAlias', b, identity({ package: 'other/component' })), /conflict/)
    assert.equal(callbacks.get('globalAlias')().owner, 'a')
    assert.deepEqual(registrations, ['globalAlias'])
    delete globalThis.Alpine
    resetAlpineLoadGateForTests()
    pass('actual load gate registers one Alpine alias and does not mutate its callback on conflict')
}
{
    resetAlpineLoadGateForTests()
    const callbacks = new Map(), events = new Map()
    globalThis.document = { addEventListener(name, callback) { events.set(name, callback) } }
    const gate = getAlpineLoadGate()
    gate.registerAlpineData('pendingAlias', a, identity())
    gate.prepareAlpineData('pendingAlias', a, identity())
    assert.throws(() => gate.registerAlpineData('pendingAlias', b, identity({ package: 'other/component' })), /conflict/)
    globalThis.Alpine = { data(name, callback) { callbacks.set(name, callback) } }
    events.get('alpine:init')()
    assert.equal(callbacks.get('pendingAlias')().owner, 'a')
    delete globalThis.Alpine; delete globalThis.document
    resetAlpineLoadGateForTests()
    pass('pending registration before Alpine startup retains the same owner and prepared source')
}
console.log(`PASS: ${checks} factory ownership groups; no test files modified.`)
