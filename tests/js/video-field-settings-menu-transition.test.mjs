import { test } from 'node:test'
import assert from 'node:assert/strict'
import {getSettingsSubmenuTransitionAttrs,isSettingsRootInactive,SETTINGS_MENU_TRANSITION_MS,SETTINGS_MENU_POPOVER_TRANSITION_MS,waitForSettingsMenuPopoverAnimation} from '../../resources/js/core/settings-menu-transition.js'

test('video settings transition exposes consistent starting, active and ending phases', () => {
    const expected={hidden:[null,'',null,null],entering:['',null,'',null],active:['',null,null,null],exiting:['',null,null,'']}
    for(const [phase,values] of Object.entries(expected)) {
        const attrs=getSettingsSubmenuTransitionAttrs(phase,'forward')
        assert.deepEqual(Object.values(attrs),[values[0],values[1],'forward',values[2],values[3]])
        assert.equal(isSettingsRootInactive('quality',phase),phase==='active')
    }
    assert.equal(isSettingsRootInactive(null,'active'),false)
    assert.equal(SETTINGS_MENU_TRANSITION_MS,250)
})
test('video popover fallback waits for its declared duration', async () => {
    const started=performance.now()
    await waitForSettingsMenuPopoverAnimation(null,SETTINGS_MENU_POPOVER_TRANSITION_MS)
    assert(performance.now()-started >= SETTINGS_MENU_POPOVER_TRANSITION_MS-25)
})
