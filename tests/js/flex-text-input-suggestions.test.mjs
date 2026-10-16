import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createInputSuggestions } from '../../resources/js/components/text-input/suggestions.js'

class Node extends EventTarget {
    children = []; dataset = {}; attributes = new Map(); hidden = false; id = 'suggestion-input'; value = ''
    classList = { toggle() {} }; append(...nodes) { this.children.push(...nodes) }
    replaceChildren(...nodes) { this.children = nodes }
    setAttribute(name,value) { this.attributes.set(name,value) }
    getAttribute(name) { return this.attributes.get(name) ?? null }
    removeAttribute(name) { this.attributes.delete(name) }
    remove() { this.removed = true }
    focus() { document.activeElement = this }
    scrollIntoView() {}
}
const tick = () => new Promise(resolve => setTimeout(resolve,0))
function fixture(options={}) {
    const input = new Node(), shell = new Node(), root = new Node(), nodes = [], surfaces = []
    input.closest = () => shell
    globalThis.document = { activeElement:input, createElement() { const node = new Node(); nodes.push(node); return node } }
    const owner = {$refs:{input},$el:root,options:{editable:true,suggestionsEnabled:true,suggestionMinLength:0,
        suggestionDebounce:0,suggestionLimit:3,messages:{suggestions_empty:'No suggestions',suggestions_empty_hint:'Enter your own value',suggestions_error:'Error',suggestions_loading:'Loading',retry:'Retry'},
        suggestions:[{value:'Laravel',label:'Laravel'},{value:'Disabled',label:'Disabled',disabled:true}],...options},commitToolValue(value) { input.value=value }}
    const instance = createInputSuggestions(owner,{createSurface(configuration) { const surface={configuration,
        updateMenuPosition() {},closeTeleportedMenu() {configuration.onClose()},dispose() {this.disposed=true}};surfaces.push(surface);return surface }})
    return {input,shell,root,owner,nodes,surfaces,instance,panel:nodes[0],list:nodes[1]}
}

test('suggestions use full-shell live width, inset the last option and cannot select disabled records',async()=>{
    const previous=globalThis.document; const f=fixture()
    try {
        f.instance.schedule();await tick()
        assert.equal(f.surfaces[0].configuration.anchor,f.shell)
        assert.equal(f.surfaces[0].configuration.ownershipAnchor,f.input)
        assert.equal(f.surfaces[0].configuration.matchTriggerWidth,true)
        assert.equal(f.surfaces[0].configuration.width,0)
        assert.equal(f.list.children.at(-1).className,'fff-select-dropdown-list-end-spacer')
        f.list.children[1].dispatchEvent(new Event('click'));assert.equal(f.input.value,'')
        f.list.children[0].dispatchEvent(new Event('click'));assert.equal(f.input.value,'Laravel')
    } finally {f.instance.dispose();globalThis.document=previous}
})

test('empty suggestions show a safe title and free-text hint; disposal removes the panel',async()=>{
    const previous=globalThis.document;const f=fixture();f.input.value='<no-match>'
    try {f.instance.schedule();await tick();const state=f.list.children[0]
        assert.equal(state.dataset.kind,'empty');assert.equal(state.getAttribute('role'),'status')
        assert.deepEqual(state.children.map(x=>x.textContent),['No suggestions','Enter your own value'])
        assert.equal(f.input.value,'<no-match>')
    } finally {f.instance.dispose();assert.equal(f.panel.removed,true);assert.equal(f.surfaces[0].disposed,true);globalThis.document=previous}
})

test('an async reply after disposal cannot restore suggestions or reopen the menu',async()=>{
    const previous=globalThis.document;let resolve;const f=fixture({asyncSuggestions:true})
    f.owner.$wire={callSchemaComponentMethod:()=>new Promise(done=>{resolve=done})}
    try {f.instance.schedule();await tick();assert.equal(f.list.children[0].dataset.kind,'loading')
        f.instance.dispose();resolve([{value:'late',label:'Late'}]);await tick()
        assert.equal(f.panel.removed,true);assert.equal(f.list.children[0].dataset.kind,'loading')
        assert.equal(f.surfaces.length,1)
    } finally {f.instance.dispose();globalThis.document=previous}
})
