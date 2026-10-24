import assert from 'node:assert/strict'
import { admitControlConfigurations, resolveControlConfiguration } from '../../resources/js/runtime/alpine/control-configurations.js'

const id = 'a'.repeat(64)
const raw = JSON.stringify({ format: 1, kind: 'control-configuration', component: 'flex-textarea', options: { shouldAutosize: true, emojiPicker: false, messages: { retry: 'Try again' } } })
const definition = value => ({ getAttribute: () => id, content: { textContent: value } })
let lookups = 0
const document = { querySelectorAll() { lookups++; return [definition(raw)] } }
const root = { ownerDocument: document, getRootNode: () => document }
const owners = Array.from({ length: 300 }, (_, index) => resolveControlConfiguration({ configurationId: id, statePath: `data.field_${index}`, initialState: `value-${index}` }, root))
assert.equal(lookups, 1)
assert.equal(owners[299].statePath, 'data.field_299')
assert.equal(owners[299].initialState, 'value-299')
owners[0].messages.retry = 'Changed for one owner'
owners[0].emojiPicker = true
assert.equal(owners[1].messages.retry, 'Try again')
assert.equal(owners[1].emojiPicker, false)
const incoming = value => ({ ownerDocument: document, querySelectorAll: () => [definition(value)] })
admitControlConfigurations(incoming(raw), document)
assert.throws(() => admitControlConfigurations(incoming(raw.replace('Try again', 'Conflicting')), document), /Conflicting/)
assert.throws(() => resolveControlConfiguration({ configurationId: id, statePath: 'data.x', emojiPicker: true }, root), /owner-local/)
const missingDocument = { querySelectorAll: () => [] }
assert.throws(() => resolveControlConfiguration({ configurationId: id, statePath: 'data.x' }, { ownerDocument: missingDocument, getRootNode: () => missingDocument }), /Missing/)
for (const invalid of [raw.replace('"format":1', '"format":2'), raw.replace('"messages":', '"initialState":'), raw.replace('"retry":', '"__proto__":')]) {
    const other = { querySelectorAll: () => [definition(invalid)] }
    assert.throws(() => resolveControlConfiguration({ configurationId: id, statePath: 'data.x' }, { ownerDocument: other, getRootNode: () => other }))
}
const inline = { statePath: 'data.inline', initialState: 'inline', shouldAutosize: false }
assert.equal(resolveControlConfiguration(inline), inline)
console.log('PASS: 300 independent owner configurations, one parse/lookup, copied nested options, incoming admission, conflict/missing/prototype rejection and inline compatibility.')
