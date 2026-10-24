import assert from 'node:assert/strict'
import test from 'node:test'
import {captureNativeEdits} from '../../resources/js/runtime/alpine/native-edit-handoff.js'

function fixture(value = 'typed', defaultValue = 'server') {
    const events = [], selections = []
    const owner = {hasAttribute: () => true, querySelectorAll: () => [input], contains: node => node === input}
    const document = {defaultView: {Event}, activeElement: null}
    const input = {value, defaultValue, disabled: false, readOnly: false, isConnected: true,
        selectionStart: 2, selectionEnd: 4, selectionDirection: 'backward', scrollLeft: 12,
        ownerDocument: document, closest: () => owner,
        dispatchEvent: event => events.push(event), setSelectionRange: (...selection) => selections.push(selection)}
    document.activeElement = input
    return {owner, input, events, selections, document}
}

test('native binding receives early edits once and focused selection survives initialization', () => {
    const {owner, input, events, selections} = fixture()
    const restore = captureNativeEdits(owner)
    input.value = input.defaultValue
    restore()
    assert.equal(input.value, 'typed')
    assert.equal(events.length, 1)
    assert.equal(events[0].type, 'input')
    assert.equal(events[0].bubbles, true)
    assert.deepEqual(selections, [[2, 4, 'backward']])
    restore()
    assert.equal(events.length, 1)
})

test('deleting all initial text is an edit; unchanged controls do not emit input', () => {
    const dirty = fixture('')
    const restore = captureNativeEdits(dirty.owner)
    dirty.input.value = 'server'
    restore()
    assert.equal(dirty.input.value, '')
    assert.equal(dirty.events.length, 1)
    const unchanged = fixture('server')
    const noop = captureNativeEdits(unchanged.owner)
    unchanged.input.value = 'new server value'
    noop()
    assert.equal(unchanged.input.value, 'new server value')
    assert.equal(unchanged.events.length, 0)
})

test('disabled, readonly, initialized and nested-owner controls do not override native state', () => {
    for (const patch of [{disabled: true}, {readOnly: true}, {_x_model: {}}, {closest: () => ({})}]) {
        const {owner, input, events} = fixture()
        Object.assign(input, patch)
        const restore = captureNativeEdits(owner)
        input.value = 'latest server'
        restore()
        assert.equal(input.value, 'latest server')
        assert.equal(events.length, 0)
    }
})

test('removal or editability revocation during initialization cancels the handover', () => {
    for (const patch of [{isConnected: false}, {disabled: true}, {readOnly: true}]) {
        const {owner, input, events} = fixture()
        const restore = captureNativeEdits(owner)
        Object.assign(input, patch, {value: 'latest server'})
        restore()
        assert.equal(input.value, 'latest server')
        assert.equal(events.length, 0)
    }
})

test('leaving the control does not move focus or selection; other owners have no snapshot', () => {
    const {owner, input, events, selections, document} = fixture()
    const restore = captureNativeEdits(owner)
    document.activeElement = null
    input.value = 'server'
    restore()
    assert.equal(input.value, 'typed')
    assert.equal(events.length, 1)
    assert.equal(selections.length, 0)
    assert.equal(captureNativeEdits({hasAttribute: () => false}), null)
})
