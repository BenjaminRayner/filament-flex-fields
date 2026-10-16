import assert from 'node:assert/strict'
import { createConsumerGraph } from '../../resources/js/runtime/assets/consumer-graph.js'
import flexCatalogComponent from '../../resources/js/components/flex-catalog.js'
import { renderCatalogOption } from '../../resources/js/select/options/catalog-presentation.js'
import { filterHeadlessOptionTree } from '../../resources/js/select/options/option-tree.js'
import { readSelectCatalog } from '../../resources/js/select/catalogs/select-options-registry.js'

const option = {value:'PL', label:'Poland & <safe>', description:'+48', image:'/pl.svg', fffCatalogRender:true, descriptionPosition:'trailing'}
const row = renderCatalogOption(option)
assert(row.includes('fff-select-option__trailing-description'))
assert(row.includes('Poland &amp; &lt;safe&gt;'))
assert(!row.includes('fff-select-option__description"'))
assert(!renderCatalogOption(option, 'trigger').includes('+48'))
assert.equal(renderCatalogOption(option), row)
assert.equal(filterHeadlessOptionTree([option], '+48', undefined, ['description']).length, 1)
assert.equal(filterHeadlessOptionTree([option], 'Poland').length, 1)
let reads = 0
const catalogDocument = {querySelectorAll(){ reads++; return [{dataset:{fffSelectCatalog:'countries'},content:{textContent:JSON.stringify([option])}}] }}
const firstRead = readSelectCatalog('countries', catalogDocument)
assert.deepEqual(firstRead, [option])
assert(Object.isFrozen(firstRead[0]))
assert.throws(() => { firstRead[0].label = 'Modified by another owner' }, TypeError)
firstRead.pop()
assert.equal(readSelectCatalog('countries', catalogDocument).length, 1)
assert.equal(readSelectCatalog('countries', catalogDocument)[0].label, option.label)
assert.equal(reads, 1)
assert.equal(readSelectCatalog('countries', {...catalogDocument}).length, 1)
assert.equal(reads, 2)
const nestedRead = readSelectCatalog('nested', {querySelectorAll(){ return [{dataset:{fffSelectCatalog:'nested'},content:{textContent:JSON.stringify([{label:'Group',options:[{...option,metadata:{region:'Europe'}}]}])}}] }})
assert(Object.isFrozen(nestedRead[0].options))
assert(Object.isFrozen(nestedRead[0].options[0].metadata))
assert.throws(() => { nestedRead[0].options[0].metadata.region = 'Changed' }, TypeError)
console.log('PASS: compact catalog escaping, trailing metadata, trigger projection, plain-text search and document-local parsing with independent arrays and immutable nested records.')

const graph = createConsumerGraph({ registry: {
    select: { stylesheets: ['/overlay.css', '/select.css'], chunks: ['/overlay.js', '/select.js'] },
    input: { stylesheets: ['/input.css'], chunks: ['/input.js'] },
    color: { stylesheets: ['/color.css', '/select.css'] },
} })
const retired = []
graph.onRefZero('/input.css', url => retired.push(url))
graph.onRefZero('/select.css', url => retired.push(url))
graph.acquire('page.select.1', 'select')
graph.acquire('page.select.2', 'select')
graph.acquire('modal.select', 'select')
graph.acquire('modal.input', 'input')
assert.equal(graph.getRefCount('/select.css'), 3)
assert.equal(graph.getRefCount('/input.css'), 1)
graph.release('modal.select'); graph.release('modal.input')
await new Promise(resolve => setTimeout(resolve, 180))
assert.equal(graph.getRefCount('/select.css'), 2)
assert.deepEqual(retired, ['/input.css'])
// Livewire remorph/reopen within the grace period must cancel pending retirement.
graph.release('page.select.1'); graph.release('page.select.2')
graph.acquire('modal.reopened', 'select')
await new Promise(resolve => setTimeout(resolve, 180))
assert.equal(graph.getRefCount('/select.css'), 1)
assert.deepEqual(retired, ['/input.css'])
// Capabilities use the actual instance plan rather than a type-wide registry union.
graph.acquire('opaque.color', 'color', ['/color.css'])
graph.acquire('format.color', 'color', ['/color.css', '/select.css', '/select.css'])
assert.equal(graph.getRefCount('/color.css'), 2)
assert.equal(graph.getRefCount('/select.css'), 2)
graph.release('format.color')
assert.equal(graph.getRefCount('/select.css'), 1)
graph.reset()
assert.equal(graph.getRetainedUrls().length, 0)
assert.equal(graph.getActiveConsumerIds().length, 0)

const catalog = flexCatalogComponent({ entries: [
    { id: 'official', categories: ['controls'], official: true, installed: true, search: 'Zażółć gęślą jaźń' },
    { id: 'custom', categories: ['controls'], official: false, installed: true, search: 'Private custom control' },
    { id: 'external', categories: ['pickers'], official: false, installed: false, search: 'Remote package' },
] })
catalog.query = 'zazolc jazn'
assert.equal(catalog.resultCount, 1)
assert(catalog.visible('official'))
catalog.resetFilters(); catalog.collection = 'community'
assert.equal(catalog.resultCount, 2)
assert(catalog.visible('custom'))
catalog.collection = 'installed'
assert.equal(catalog.resultCount, 2)
catalog.category = 'pickers'
assert.equal(catalog.resultCount, 0)
catalog.resetFilters(); catalog.query = 'remote'
assert.equal(catalog.groupVisible(['official', 'custom']), false)
assert.equal(catalog.groupVisible(['external']), true)
const deck = catalog.promotionDeck(['one','two'], {pending:false})
assert.equal(deck.slideReady(['image']), false)
deck.imageSettled('image', false); assert.equal(deck.slideReady(['image']), true)
deck.move(-1); assert.equal(deck.active, 1)
deck.go(4); assert.equal(deck.active, 0)
deck.pointerDown({pointerType:'touch',clientX:200,clientY:0})
deck.pointerUp({clientX:100,clientY:150}); assert.equal(deck.active, 0)
const pendingDeck = catalog.promotionDeck(['one'], {pending:true})
assert.equal(pendingDeck.slideReady([]), false)
console.log('PASS: page/modal shared refs, unused CSS retirement, remorph cancellation, per-instance capabilities, reset and normalized cached catalog filtering.')
