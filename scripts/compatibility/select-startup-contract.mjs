import assert from 'node:assert/strict'
import { readRenderedSelectConfiguration } from '../../resources/js/select/configuration/rendered-configuration.js'
import { createSelectEngineBindingMixin } from '../../resources/js/select/adapters/alpine/engine-binding.js'
import { createHeadlessComboboxEngineSyncMixin } from '../../resources/js/components/select-field/headless-combobox-engine-sync.js'

const values = { options: [{value: 'a', label: '</template><script>alert(1)</script>'}], multiple: false }
const root = { querySelector(selector) {
    assert.equal(selector, ':scope > template[data-fff-select-configuration]')
    return {content: {textContent: JSON.stringify(values)}}
} }
assert.deepEqual(readRenderedSelectConfiguration(root), values)
assert.throws(() => readRenderedSelectConfiguration({querySelector: () => null}), /missing/)
for (const text of ['[]', 'null', 'false', '{broken']) {
    assert.throws(() => readRenderedSelectConfiguration({querySelector: () => ({content: {textContent: text}})}))
}
const sibling = {querySelector: () => ({content: {textContent: '{"options":[]}'}})}
assert.deepEqual(readRenderedSelectConfiguration(sibling), {options: []})

const options = Array.from({length: 1000}, (_, index) => ({value: String(index), label: `Option ${index}`}))
const host = {
    ...createSelectEngineBindingMixin({engineConfig: {}}),
    ...createHeadlessComboboxEngineSyncMixin(),
    options, multiple: false, searchable: false, optionsLimit: 50,
    hasDynamicSearchResults: false, virtualizeThreshold: 100, virtualWindowSize: 20,
    _selectDisposed: false, comboboxQuery: '', comboboxSelectedValues: [],
    _syncFromEngine() {},
}
host.createSelectEngine([])
const setOptions = host._engine.setOptions.bind(host._engine)
let replacements = 0
host._engine.setOptions = (next) => { replacements++; return setOptions(next) }
host.syncEngineOptions()
assert.equal(replacements, 0, 'Initialization immediately replaced the identical option collection.')
options[0].label = 'Changed in place'
host.syncEngineOptions()
assert.equal(replacements, 1, 'Same-reference option edits were ignored.')
options[0].description = 'Changed rich metadata'
host.syncEngineOptions()
assert.equal(replacements, 2, 'Rich metadata did not invalidate the engine snapshot.')
host.syncEngineOptions()
assert.equal(replacements, 2)
host._engine.destroy()
console.log('PASS: inert owner-local configuration, malformed payload rejection, independent instances, one initial engine snapshot and mutable/rich option invalidation.')
