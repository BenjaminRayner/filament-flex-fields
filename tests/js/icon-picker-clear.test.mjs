import assert from 'node:assert/strict'
import {test} from 'node:test'
import {createIconSelect} from '../../resources/js/select/adapters/icon-picker/controller.js'

function picker(configuration = {}) {
    let commands = 0
    const owner = createIconSelect({clearable:true, state:'heroicon-o-star', initialSelectedName:'heroicon-o-star', inline:true,
        layout:'icons', availableSets:[], gridColumns:3, perPage:12, labels:{}, ...configuration})
    owner._engine = {setSelectedValues(values) {assert.deepEqual(values,[]); commands++}}
    owner._syncFromEngine = () => {owner.state = null}
    owner.syncClearablePresentation = () => {}
    owner.usesInlineSearchTriggerInput = () => false
    return {owner, commands:()=>commands}
}

test('the real Icon Picker clears through its shared Select selection engine', () => {
    const {owner,commands} = picker()
    owner.text = 'Contact'
    owner.clearSelection()
    assert.equal(owner.state,null)
    assert.equal(commands(),1)
    assert.equal(owner.text,'Contact')
})

for (const [configuration, disposed] of [[{clearable:false},false],[{readOnly:true},false],[{},true]]) {
    test(`clear respects ${disposed ? 'disposal' : Object.keys(configuration)[0]}`, () => {
        const {owner,commands} = picker(configuration)
        owner._selectDisposed = disposed
        owner.clearSelection()
        assert.equal(owner.state,'heroicon-o-star')
        assert.equal(commands(),0)
    })
}
