import { test } from 'node:test'
import assert from 'node:assert/strict'
import { createIconSelect } from '../../resources/js/select/adapters/icon-picker/controller.js'
import { resolveTeleportedMenuHorizontalLeft, resolveTeleportedMenuVerticalPlacement } from '../../resources/js/core/teleported-menu-position.js'

const configuration = inline => ({inline,layout:'icons',availableSets:[],gridColumns:8,perPage:48,labels:{}})
test('inline icon menu anchors outside the full input and aligns to its end', () => {
    const component = createIconSelect(configuration(true)), input = {}, button = {}
    component.$el = {closest: selector => selector === '.fff-flex-text-input__shell' ? input : null}
    component.$refs = {headlessTrigger:button}
    assert.equal(component.resolveMenuTriggerElement(), input)
    assert.equal(component.dropdownAlign, 'end')
    assert.equal(component.matchTriggerWidth, false)
    assert.equal(resolveTeleportedMenuHorizontalLeft({triggerRect:{left:800,right:1144},menuWidth:400,align:component.dropdownAlign,windowWidth:1440}), 744)
    assert.deepEqual(resolveTeleportedMenuVerticalPlacement({triggerRect:{top:300,bottom:340},panelHeight:280,windowHeight:900}), {top:346,opensAbove:false})
    assert.deepEqual(resolveTeleportedMenuVerticalPlacement({triggerRect:{top:750,bottom:790},panelHeight:280,windowHeight:900}), {top:464,opensAbove:true})
})
test('standalone icon picker retains its ordinary trigger and start alignment', () => {
    const component = createIconSelect(configuration(false)), button = {}
    component.$el = {closest:()=>null}; component.$refs = {headlessTrigger:button}
    assert.equal(component.resolveMenuTriggerElement(), button)
    assert.equal(component.dropdownAlign, 'start')
})
test('end alignment mirrors in RTL and remains inside narrow desktop viewports', () => {
    assert.equal(resolveTeleportedMenuHorizontalLeft({triggerRect:{left:300,right:600},menuWidth:400,align:'end',direction:'rtl',windowWidth:1000}),300)
    assert.equal(resolveTeleportedMenuHorizontalLeft({triggerRect:{left:10,right:90},menuWidth:400,align:'end',windowWidth:720}),16)
})


test('PHP-driven icon changes fetch only missing previews and reject late responses after clear or disposal', async () => {
    const { createIconSelectionPreview } = await import('../../resources/js/select/adapters/icon-picker/preview.js')
    let publish = [], flights = []
    const preview = createIconSelectionPreview({resolve:name=>name === 'known' ? '<svg>known</svg>' : '',
        fetch:name=>new Promise(resolve=>flights.push({name,resolve})), publish:(name,html)=>publish.push([name,html])})
    await preview.update('known'); assert.equal(flights.length,0)
    const first = preview.update('first'), second = preview.update('second')
    flights[1].resolve([{name:'second',html:'<svg>second</svg>'}]); await second
    flights[0].resolve([{name:'first',html:'<svg>first</svg>'}]); await first
    assert.deepEqual(publish.at(-1), ['second','<svg>second</svg>'])
    await preview.update('second'); assert.equal(flights.length,2)
    const third = preview.update('third'); await preview.update(null)
    flights[2].resolve([{name:'third',html:'<svg>third</svg>'}]); await third
    assert.deepEqual(publish.at(-1),['',''])
    const fourth = preview.update('fourth'); preview.dispose()
    flights[3].resolve([{name:'fourth',html:'<svg>fourth</svg>'}]); await fourth
    assert.deepEqual(publish.at(-1),['fourth',''])
})
