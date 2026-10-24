import assert from 'node:assert/strict'
import { admitControlConfigurations, resolveControlMetadata, materializeControlMetadata } from '../../resources/js/runtime/alpine/control-configurations.js'
import { prepareCatalogSelectConfiguration } from '../../resources/js/select/catalogs/select-options-registry.js'
const id = 'b'.repeat(64)
const raw = JSON.stringify({ format: 1, kind: 'control-configuration', component: 'select-catalog', options: { catalogKey: 'fixture', options: [{ value: 'PL', label: 'Poland', isDisabled: false }] } })
const node = text => ({ getAttribute: () => id, content: { textContent: text } })
const doc = { nodeType: 9, getRootNode() { return this }, querySelectorAll(selector) { return selector.includes('data-fff-select-catalog-ref') ? [reference] : selector.includes('configuration-definition') ? [node(raw)] : [] } }
const reference = { dataset: { fffSelectCatalogRef: 'fixture' }, getAttribute: () => id, ownerDocument: doc, getRootNode: () => doc }
const one = prepareCatalogSelectConfiguration({ optionsRegistryKey: 'fixture', state: 'PL' }, doc)
const two = prepareCatalogSelectConfiguration({ optionsRegistryKey: 'fixture', state: null }, doc)
assert.notEqual(one.options, two.options)
assert.equal(one.options[0], two.options[0])
assert.equal(Object.isFrozen(one.options[0]), true)
one.options.push({ value: 'US' })
assert.equal(two.options.length, 1)
assert.equal(two.state, null)
assert.throws(() => { one.options[0].label = 'Changed' }, TypeError)
assert.throws(() => resolveControlMetadata(id, 'phone-catalog', reference), /mismatched/)
assert.throws(() => admitControlConfigurations({ querySelectorAll: () => [node(raw.replace('Poland', 'Changed'))] }, doc), /Conflicting/)
const configRaw = JSON.stringify({ format: 1, kind: 'control-configuration', component: 'select-configuration', options: { multiple: false, options: [], selectEmptyStateHints: { loading: 'Wait' } } })
const other = { nodeType: 9, getRootNode() { return this }, querySelectorAll: () => [node(configRaw)] }
const root = { ownerDocument: other, getRootNode: () => other }
const left = materializeControlMetadata(id, 'select-configuration', root), right = materializeControlMetadata(id, 'select-configuration', root)
left.options.push({ value: 'private' }); left.selectEmptyStateHints.loading = 'Changed'
assert.equal(right.options.length, 0); assert.equal(right.selectEmptyStateHints.loading, 'Wait')
for (const invalid of [raw.replace('"label":', '"__proto__":'), raw.replace('"format":1', '"format":2')]) {
    const d = { nodeType: 9, getRootNode() { return this }, querySelectorAll: () => [node(invalid)] }
    assert.throws(() => resolveControlMetadata(id, 'select-catalog', { ownerDocument: d, getRootNode: () => d }))
}
console.log('PASS: shared immutable catalog records with independent array/selection state; type/prototype/conflict rejection; fresh nested mutable presentation options per owner.')
