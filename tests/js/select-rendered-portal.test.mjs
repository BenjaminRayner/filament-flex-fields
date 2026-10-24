import assert from 'node:assert/strict'
import {test} from 'node:test'
import {prepareRenderedSelectPortal} from '../../resources/js/select/configuration/rendered-portal.js'

const id = 'a'.repeat(64)
const html = '<template x-teleport="body"><div x-bind:id="menuDomId">compiled</div></template>'
const definition = () => ({content: {textContent: JSON.stringify({format: 1, kind: 'alpine-portal', html})}})
function fixture(sharedDefinition = definition()) {
    let assignments = 0
    let content = ''
    const template = {set innerHTML(value) {assignments++; content = value}}
    const ancestor = {parentElement: null, querySelectorAll: () => sharedDefinition ? [sharedDefinition] : []}
    const root = {parentElement: ancestor, getAttribute: () => id, querySelector: () => template, querySelectorAll: () => []}
    return {root, template, reads: () => ({assignments, content})}
}

test('closed owners retain independent inert templates and mount compiled markup exactly once on intent', () => {
    const shared = definition()
    const first = fixture(shared)
    const second = fixture(shared)
    const openFirst = prepareRenderedSelectPortal(first.root)
    const openSecond = prepareRenderedSelectPortal(second.root)
    assert.equal(first.reads().assignments, 0)
    openFirst(); openFirst()
    assert.deepEqual(first.reads(), {assignments: 1, content: html})
    assert.equal(second.reads().assignments, 0)
    // A later fragment can retire its definition. Already admitted owners retain
    // their own immutable projection and never read a sibling's runtime state.
    shared.content.textContent = ''
    openSecond()
    assert.deepEqual(second.reads(), {assignments: 1, content: html})
})

test('inline custom transport remains untouched and invalid definitions fail before HTML insertion', () => {
    assert.doesNotThrow(() => prepareRenderedSelectPortal({getAttribute: () => null})())
    const missing = fixture(null)
    assert.throws(() => prepareRenderedSelectPortal(missing.root), /missing/)
    for (const raw of ['null', '[]', '{broken', JSON.stringify({format: 1, kind: 'alpine-portal', html: '<script>bad()</script>'})]) {
        const owner = fixture({content: {textContent: raw}})
        assert.throws(() => prepareRenderedSelectPortal(owner.root))
        assert.equal(owner.reads().assignments, 0)
    }
})

test('definition mutation and ambiguous local fragments cannot silently replace compiled markup', () => {
    const shared = definition()
    prepareRenderedSelectPortal(fixture(shared).root)
    shared.content.textContent = shared.content.textContent.replace('compiled', 'changed')
    assert.throws(() => prepareRenderedSelectPortal(fixture(shared).root), /Conflicting/)
    const owner = fixture()
    owner.root.parentElement.querySelectorAll = () => [definition(), definition()]
    assert.throws(() => prepareRenderedSelectPortal(owner.root), /Conflicting/)
})

test('a partial rooted at the interactive owner resolves its own direct-child definition', () => {
    const owner = fixture(null)
    owner.root.querySelectorAll = () => [definition()]
    const mount = prepareRenderedSelectPortal(owner.root)
    mount()
    assert.deepEqual(owner.reads(), {assignments: 1, content: html})
})
