import assert from 'node:assert/strict'
import { readFileSync, readdirSync } from 'node:fs'
import { createSearchableSelectMenuMixin } from '../../resources/js/overlay/menu/menu-controller.js'
import { publishOwnerData } from '../../resources/js/runtime/alpine/owner-data.js'

const first = createSearchableSelectMenuMixin()
const second = createSearchableSelectMenuMixin()
assert.notEqual(first.menuScrollParents, second.menuScrollParents)
const parent = {_geocodingRequests:{dispose(){throw Error('Child disposed parent transport')}},searchDebounceTimer:99,
    _iconWindowSession:{dispose(){throw Error('Child disposed parent virtualizer')}}}
const merged = owner => new Proxy(owner, {
    get(target,key) { return Object.hasOwn(target,key) ? target[key] : parent[key] },
    set(target,key,value) { (Object.hasOwn(target,key) ? target : parent)[key] = value; return true },
})
const child = merged(first)
let cleaned = 0
child.ensurePickerResourceScope().defer(()=>{cleaned++})
child.disposePickerResources(); child.disposePickerResources()
assert.equal(cleaned,1)
assert.equal(parent.searchDebounceTimer,99)
for (const key of ['measureVirtualListViewport','resetVirtualListScroll','afterOverlayPanelOpened','onOverlaySheetGeometry',
    'resolveMenuTriggerElement','resolveMenuMinWidth','resolveMatchTriggerWidth','position']) {
    assert(Object.hasOwn(first,key))
    assert.equal(first[key],null)
}
const writes = new Set()
for (const name of readdirSync(new URL('../../resources/js/overlay/menu/',import.meta.url)).filter(name=>name.endsWith('.js'))) {
    const text = readFileSync(new URL(`../../resources/js/overlay/menu/${name}`,import.meta.url),'utf8')
    for (const match of text.matchAll(/this\.([\w$]+)\s*(?:\?\?=|\|\|=|\+\+|--|\+=|-=|=(?!=|>))/g)) {
        // The imperative adapter declares its own anchor, rather than Alpine's magic $el.
        if (name === 'imperative-surface.js' && match[1] === '$el') {
            assert.match(text, /\$el:\s*anchor/, 'Imperative retarget must write its declared local anchor')
            continue
        }
        writes.add(match[1])
    }
}
for (const key of writes) assert(Object.hasOwn(first,key),`Undeclared menu owner state: ${key}`)
const ownerA={}, ownerB={}
const wrapper = owner => ({_pickerHostElement:{_x_dataStack:[owner,parent]}})
publishOwnerData(wrapper(ownerA),{behavior(){return 'a'}})
publishOwnerData(wrapper(ownerB),{behavior(){return 'b'}})
assert.equal(ownerA.behavior(),'a'); assert.equal(ownerB.behavior(),'b'); assert(!Object.hasOwn(parent,'behavior'))
console.log('PASS: menu state/hooks are local, mutable arrays are distinct, lazy behavior belongs to its owner, and child cleanup cannot touch an ancestor transport, timer or virtualizer.')
