import { test } from 'node:test'
import assert from 'node:assert/strict'
import { resolveVideoHotkeyAction } from '../../resources/js/video/values.js'
import { createTapGestureTracker, beginTapGesture, resolveTapGesture, resolveGestureRegion } from '../../resources/js/video/values.js'
class BrowserElement {constructor(tag) {this.tagName=tag}}
globalThis.Element=BrowserElement

test('video hotkeys handle media commands but preserve editable fields and modifiers', () => {
    const event={altKey:false,ctrlKey:false,metaKey:false,defaultPrevented:false,target:new BrowserElement('DIV')}
    for(const [key,action] of [[' ','togglePaused'],['f','toggleFullscreen'],['ArrowUp','volumeUp']]) assert.equal(resolveVideoHotkeyAction({...event,key}),action)
    for(const tag of ['INPUT','TEXTAREA','SELECT']) assert.equal(resolveVideoHotkeyAction({...event,key:' ',target:new BrowserElement(tag)}),null)
    assert.equal(resolveVideoHotkeyAction({...event,key:'f',ctrlKey:true}),null)
})
test('video gesture regions and same-region double tap remain consistent', () => {
    const rect={left:0,width:300}, tracker=createTapGestureTracker()
    assert.deepEqual([50,150,250].map(x => resolveGestureRegion(x,rect)),['left','center','right'])
    beginTapGesture(tracker,{clientX:150})
    assert.equal(resolveTapGesture(tracker,{clientX:150},rect).isDoubleTap,false)
    beginTapGesture(tracker,{clientX:150})
    assert.equal(resolveTapGesture(tracker,{clientX:150},rect).isDoubleTap,true)
    beginTapGesture(tracker,{clientX:50})
    assert.equal(resolveTapGesture(tracker,{clientX:50},rect).isDoubleTap,false)
})
