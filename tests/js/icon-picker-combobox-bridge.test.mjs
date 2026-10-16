import assert from 'node:assert/strict'
import { test } from 'node:test'
import { createIconPickerComboboxBridge } from '../../resources/js/core/icon-picker-combobox-bridge.js'

test('legacy icon hooks delegate to the canonical active selection without a second engine', () => {
    let moves = [], selected = 0, synced = 0
    const component = {
        loadedIconItems: [{name:'heroicon-o-star',label:'Star'}],
        syncActiveIconIndex() {synced++},
        moveActiveIcon(delta) {moves.push(delta)},
        selectActiveIcon() {selected++},
    }
    createIconPickerComboboxBridge(component)
    component.iconComboboxMoveHighlight(1)
    component.iconComboboxSelectHighlighted()
    assert.equal(synced,1)
    assert.deepEqual(moves,[1])
    assert.equal(selected,1)
    assert.equal(component._iconEngine,undefined)
})
test('icon bridge reads the existing virtual viewport without creating another virtualizer', () => {
    const items = Array.from({length:120},(_,i) => ({name:`icon-${i}`}))
    const component = {loadedIconItems:items, resolveLiveResultsWindow:() => ({startIndex:20,endIndex:30,slice:items.slice(20,30)})}
    createIconPickerComboboxBridge(component)
    const window = component.iconComboboxFilteredWindow()
    assert.deepEqual(window.meta,{startIndex:20,endIndex:30,total:120})
    assert.equal(window.options.length,10)
    assert.equal(window.options[0].value,'icon-20')
    assert.equal(component.onOverlayEngineScroll,undefined)
})
