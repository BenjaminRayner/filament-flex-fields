import assert from 'node:assert/strict'
import { createIconOptionSource } from '../../resources/js/select/adapters/icon-picker/source.js'
import { createIconSelect } from '../../resources/js/select/adapters/icon-picker/controller.js'
import { createVirtualWindowMixin } from '../../resources/js/select/adapters/virtual/window.js'
let set = null, calls = []
const source = createIconOptionSource({perPage:2,activeSet:()=>set,request:async(method, params)=>{
    calls.push({method,params})
    return {items:[{value:'a',label:'A',preview:'<svg></svg>'}],hasMore:false,cursor:null}
}})
assert.equal((await source.search({query:'a',cursor:null})).items[0].value,'a')
set='hugeicons';await source.search({query:'a',cursor:'2:x'})
assert.equal(calls[1].params.set,'hugeicons');assert.equal(calls[1].params.cursor,'2:x')
const abort = new AbortController();abort.abort()
await assert.rejects(()=>source.search({query:'a',signal:abort.signal}),{name:'AbortError'})
source.dispose();await assert.rejects(()=>source.search({query:'a'}),{name:'AbortError'})
let release
const pendingSource = createIconOptionSource({perPage:1,activeSet:()=>null,request:()=>new Promise(r=>release=r)})
const flight=pendingSource.search({query:'a'});pendingSource.dispose()
release({items:[{value:'a',label:'A',preview:''}],hasMore:false});await assert.rejects(()=>flight,{name:'AbortError'})
const malformed = createIconOptionSource({perPage:1,activeSet:()=>null,request:async()=>({items:[{value:'a',label:'A',preview:'x'.repeat(65537)}],hasMore:false})})
await assert.rejects(()=>malformed.search({query:''}),TypeError)
const config = {state:null,componentKey:'icon',perPage:64,availableSets:[{key:'hugeicons',label:'Hugeicons'}],layout:'icons',gridColumns:8,labels:{},clearable:true,readOnly:false,closeOnSelect:false}
const field=createIconSelect(config)
assert.equal(field.hasPaginatedSearchResults,true);assert.equal(field.isGridLayout,true);assert.equal(field.optionsLimit,0)
assert.equal(field.virtualGridRowHeight,44)
assert.equal(field._virtualGridColumns,8)
assert.equal(field._virtualGridGap,4)
let selected=[],closed=0
Object.assign(field,{optionRecord:value=>({value,label:value,preview:'<svg></svg>'}),isHeadlessOptionDisabled:()=>false,
    rememberSelectedOption(){},_engine:{selectValue:value=>selected.push(value)},_syncFromEngine(){},comboboxCloseMenu(){closed++}})
field.comboboxSelectValue('a');assert.deepEqual(selected,['a']);assert.equal(closed,0);assert.equal(field.iconHtml,'<svg></svg>')
field.disabled=true;field.comboboxSelectValue('b');assert.deepEqual(selected,['a'])
let schedules=0,clears=0
field.disabled=false;Object.assign(field,{cancelRelationshipSearch(){clears++},applyRemoteOptions(){},scheduleAsyncSearch(){schedules++}})
field.selectIconSet('missing');assert.equal(schedules,0)
field.selectIconSet('hugeicons');assert.equal(schedules,1);assert.equal(clears,1)
field.selectIconSet('hugeicons');assert.equal(schedules,1)
field._selectDisposed=true;field.selectIconSet(null);assert.equal(schedules,1)
const virtual=createVirtualWindowMixin({HEADLESS_SELECT_VIRTUAL_OVERSCAN:10})
assert.equal(virtual.resolveVirtualRowHeight.call({isGridLayout:true,virtualGridRowHeight:44},{type:'option'}),44)
console.log('PASS: IconPicker uses real Select pagination, provider cancellation, bounded payloads, owned selection/set changes and compact grid estimates.')

const {hasPageViewportDemand}=await import('../../resources/js/select/virtualization/page-demand.js')
assert.equal(hasPageViewportDemand({scrollTop:960,viewportHeight:320,totalHeight:1280}),true)
assert.equal(hasPageViewportDemand({scrollTop:960,viewportHeight:320,totalHeight:2560}),false,'Appending a page removes demand without requiring another user gesture')
assert.equal(hasPageViewportDemand({scrollTop:0,viewportHeight:320,totalHeight:640}),false)
assert.equal(hasPageViewportDemand({scrollTop:0,viewportHeight:0,totalHeight:640}),false)
const {readFileSync}=await import('node:fs')
const template=readFileSync(new URL('../../resources/views/forms/components/partials/select-field/option-list.blade.php',import.meta.url),'utf8')
assert.match(template,/class="fff-select-max-items-message"[\s\S]*?<\/div>\s*<\/div>\s*@include\('filament-flex-fields::forms.components.partials.select-field.load-more'\)/)
console.log('PASS: pagination demand uses the full virtual extent; the sentinel is after bottom padding, preventing runaway loads.')

const {createSelectPageViewportMixin}=await import('../../resources/js/select/adapters/livewire/page-viewport.js')
let pageCalls=0
const scrollRoot={scrollTop:560,clientHeight:224,scrollHeight:800}
const demandOwner={...createSelectPageViewportMixin(),comboboxOpen:true,hasPaginatedSearchResults:true,searchResultsHasMore:true,
    shouldShowHeadlessDropdownOptions:()=>true,shouldVirtualizeDropdown:()=>false,
    $refs:{headlessOptionsList:scrollRoot,headlessLoadMoreSentinel:{}},loadMoreSearchResults(){pageCalls++}}
// A scroll/resize demand must work even without a fresh IntersectionObserver edge.
demandOwner.observeHeadlessLoadMore();assert.equal(pageCalls,1)
scrollRoot.scrollHeight=1600;demandOwner.observeHeadlessLoadMore();assert.equal(pageCalls,1)
demandOwner.searchPending=true;scrollRoot.scrollHeight=800;demandOwner.observeHeadlessLoadMore();assert.equal(pageCalls,1)
console.log('PASS: settled grid/scroll demand cannot starve pagination or trigger another page after append.')
