import assert from 'node:assert/strict'
import { createLocationOptionSource } from '../../resources/js/location/search/source.js'
import { createLocationSelect } from '../../resources/js/location/search/controller.js'
import { createSelectConfiguration } from '../../resources/js/select/adapters/alpine/configuration.js'
import { createHeadlessComboboxLivewireMixin } from '../../resources/js/components/select-field/headless-combobox-livewire.js'
import { createRequestCoordinator } from '../../resources/js/select/async/request-coordinator.js'
import { createHeadlessComboboxEngineSyncMixin } from '../../resources/js/components/select-field/headless-combobox-engine-sync.js'
import { createSelectSearchLoaderMixin } from '../../resources/js/select/adapters/livewire/search-loader.js'

const configuration = {minSearchLength:2,searchDebounce:0,disabled:false,initialLabel:'Warsaw',select:{},labels:{queryTooLong:'Too long',search:'Search',searchLoading:'Searching',searchNoResults:'No results'},geocoder:{limit:6}}
let calls = 0
const provider = async ({query}) => {calls++;return [{id:query,place_name:`Place ${query}`,center:[21,52]},{id:query,place_name:'Duplicate'}]}
const source = createLocationOptionSource(configuration,provider)
assert.deepEqual(await source.search({query:'a'}),[]);assert.equal(calls,0)
assert.equal((await source.search({query:'Warsaw'})).length,1)
await source.search({query:'Warsaw'});assert.equal(calls,1)
assert.equal(source.record('Warsaw').feature.center[0],21)
await assert.rejects(()=>source.search({query:'x'.repeat(257)}),RangeError)
const controller = new AbortController();controller.abort()
assert.deepEqual(await source.search({query:'London',signal:controller.signal}),[])
for(let index=0;index<18;index++) await source.search({query:`Place${index}`})
await source.search({query:'Warsaw'});assert.equal(calls,20,'Bounded cache evicts the oldest query')
source.dispose();assert.equal(source.record('Warsaw'),null)
assert.deepEqual(await source.search({query:'Warsaw'}),[])

const dataSource = {search:async()=>[]}
const prepared = createSelectConfiguration({dataSource,hasPaginatedSearchResults:true})
assert.equal(prepared.state.hasDynamicSearchResults,true)
assert.equal(prepared.capabilities.livewire,true)
assert.equal(prepared.livewireConfig.dataSource,dataSource)
const field = createLocationSelect(configuration)
assert.equal(field.inlineSearch,true);assert.equal(field.hasDynamicSearchResults,true)
assert.equal(field.state,'__location_initial__');assert.equal(field.comboboxQuery,'')
// Client providers must not subscribe to unrelated Livewire commits or resolve labels remotely.
let watchers=0
const integration = createHeadlessComboboxLivewireMixin({dataSource,hasDynamicSearchResults:true})
Object.assign(integration,{_selectDisposed:false,seedLabelRepository(){},$watch(){watchers++},
    bindSelectedOptionLabelRefreshListener(){throw new Error('Unexpected global listener')},
    bindSelectDependencies(){throw new Error('Unexpected dependency watcher')},
    bindDynamicOptionsInvalidation(){throw new Error('Unexpected commit hook')}})
integration.initLivewireIntegration();assert.equal(integration._relationshipAdapter,null);assert.equal(watchers,2)

// The exact loader used by paginated Select owns client requests as well.
const flights=[]
const owner={...createSelectSearchLoaderMixin(),_selectSearchRequests:createRequestCoordinator(),
    _selectOptionsRequests:createRequestCoordinator(),_selectDisposed:false,hasDynamicSearchResults:true,
    hasPaginatedSearchResults:false,comboboxQuery:'old',comboboxOpen:true,maxPageBytes:1024,
    _selectDataSource:{search:({query,signal})=>new Promise(resolve=>flights.push({query,signal,resolve}))},
    resetPaginatedSearchState(){this.searchResultsCursor=null;this.searchResultsHasMore=false;this._searchPageCursors=new Set()},
    applyRemoteOptions(rows){this.painted=rows},populateLabelRepositoryFromOptions(){},$nextTick(){},captureSelectRequestFailure(){}}
const old=owner.fetchSearchResults('old');owner.comboboxQuery='new';const next=owner.fetchSearchResults('new')
assert.equal(flights[0].signal.aborted,true)
flights[1].resolve([{value:'new',label:'New'}]);await next
flights[0].resolve([{value:'old',label:'Old'}]);await old
assert.equal(owner.painted[0].value,'new')
owner.comboboxQuery='bad';const bad=owner.fetchSearchResults('bad')
flights[2].resolve([{value:'bad',label:'x'.repeat(2048)}]);await bad
assert.equal(owner.painted[0].value,'new');assert(owner.searchError instanceof RangeError)
owner.comboboxQuery='disposed';const pending=owner.fetchSearchResults('disposed')
owner._selectDisposed=true;owner._selectSearchRequests.dispose()
assert.equal(flights[3].signal.aborted,true);flights[3].resolve([{value:'disposed',label:'Disposed'}]);await pending
assert.equal(owner.painted[0].value,'new')
console.log('PASS: shared Select client provider, cancellation/stale/disposed flights, byte-budget rejection, no global subscriptions; bounded geocoder cache, duplicates and inline control.')

const projection={...createHeadlessComboboxEngineSyncMixin(),hasDynamicSearchResults:true,comboboxOpen:false,comboboxQuery:'Selected label',comboboxSelectedValues:['a'],usesInlineSearchTriggerInput:()=>true,
    _engine:{getSnapshot:()=>({query:'',highlightedIndex:-1,selectedValues:['a']})}}
projection._syncFromEngine();assert.equal(projection.comboboxQuery,'Selected label')
projection.comboboxOpen=true;projection._syncFromEngine();assert.equal(projection.comboboxQuery,'')
console.log('PASS: closed inline fields keep selected labels while an open query follows its engine.')

// Focus/blank input must never open geocoding, including configurations with min=0.
let opened = 0, closed = 0, cancelled = 0
field.comboboxOpenMenu = () => { opened++ }
field.onInlineSearchFocus()
assert.equal(opened, 0)
field.comboboxSetQuery = query => { field.comboboxQuery = query }
field.isTriggerLabelSelected = () => false
field.usesInlineSearchTriggerInput = () => true
field.comboboxCloseMenu = () => { closed++; field.comboboxOpen = false }
field.cancelRelationshipSearch = () => { cancelled++ }
field.onInlineSearchInput({target:{value:'Warsaw'}})
assert.equal(opened, 1)
field.comboboxOpen = true
field.onInlineSearchInput({target:{value:'  '}})
assert.equal(closed, 1); assert.equal(cancelled, 1)
const zeroMinimum = createLocationOptionSource({...configuration,minSearchLength:0}, async () => { throw new Error('Blank query reached provider') })
assert.deepEqual(await zeroMinimum.search({query:'  '}),[])
zeroMinimum.dispose()
console.log('PASS: focus does not request geocoding; nonempty edits open Select; empty queries cancel/dismiss and never reach the provider.')
