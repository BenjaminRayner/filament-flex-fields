import assert from 'node:assert/strict'
import { createMenuPositioningMixin } from '../../resources/js/overlay/menu/positioning.js'
import { createLayoutPositionMixin } from '../../resources/js/select/adapters/layout/position.js'

// Drive the production open-session scheduler one paint at a time. These
// observations are independent of browser animation duration or test suites.
const frames = new Map(), ticks = []
let sequence = 0
globalThis.requestAnimationFrame = callback => { const id = ++sequence; frames.set(id, callback); return id }
globalThis.cancelAnimationFrame = id => frames.delete(id)
globalThis.window = { innerWidth: 1280, matchMedia: () => ({ matches: false }) }
const classes = new Set()
const menu = { dataset: {}, style: {}, offsetWidth: 230,
    classList: { contains: name => classes.has(name), add: name => classes.add(name), remove: name => classes.delete(name) } }
const events = []
const host = {
    ...createMenuPositioningMixin({ openKey: 'open', readyKey: 'ready', triggerRef: 'trigger', menuRef: 'menu' }),
    open: true, ready: false, _surfaceOpenGeneration: 0,
    $nextTick: callback => ticks.push(callback),
    resolveMenuTriggerRef: () => ({}), resolveMenuElement: () => menu,
    updateMenuPosition({ markReady }) {
        assert.equal(this.ready, false, 'First open must measure before readiness')
        menu.style.top = '80px'
        events.push('position')
        if (markReady) this.ready = true
    },
}
function paint() { const callbacks = [...frames.values()]; frames.clear(); callbacks.forEach(callback => callback()) }
host.scheduleMenuPosition({ onAnchored: () => { assert.equal(host.ready, false); events.push('anchor') } })
assert.equal(host.ready, false)
assert.equal(classes.has('is-open'), false)
ticks.splice(0).forEach(callback => callback())
assert.equal(classes.has('is-open'), false)
assert.equal(host.ready, false)
assert.equal(menu.style.top, '80px', 'Hidden geometry is measured after Alpine render, without an idle paint')
paint()
assert.equal(host.ready, true)
assert.equal(menu.style.top, '80px')
assert.equal(classes.has('is-open'), false)
assert.equal(events.at(-1), 'anchor')
paint()
assert.equal(classes.has('is-open'), true)
host._surfaceOpenSession.dispose()

// A close while Alpine's render is pending must cancel all queued reveals.
classes.clear(); host.ready = false; host.open = true
host.scheduleMenuPosition()
host.open = false; host._surfaceOpenSession.dispose()
ticks.splice(0).forEach(callback => callback()); paint(); paint()
assert.equal(classes.has('is-open'), false)
console.log('PASS: positioning and anchoring precede readiness/reveal; cancelled opens cannot paint stale panels.')

// Previously measured panels still need a closed animation frame when reopened.
classes.clear(); host.ready = true; host.open = true
host.updateMenuPosition = ({markReady}) => { if(markReady) host.ready = true }
host.scheduleMenuPosition()
ticks.splice(0).forEach(callback => callback())
assert.equal(classes.has('is-open'),false)
paint(); assert.equal(host.ready,true); assert.equal(classes.has('is-open'),false)
paint(); assert.equal(classes.has('is-open'),true)
host._surfaceOpenSession.dispose()
console.log('PASS: reopening a measured panel retains a closed frame before enter; layout updates of an already-open panel do not restart it.')

// The production Select adapter must preserve the shared anchor's hidden phase.
const readiness = []
const layout = {...createLayoutPositionMixin({selectMenu:{updateMenuPosition(options){readiness.push(options.markReady)}}}),
    menuReady:false, resolveMenuElement:()=>menu, resolveMenuTriggerElement:()=>({}),
    applyHeadlessDropdownWidthLayout(){}, isSheetPresentation:()=>false, canOptionLabelsWrap:true}
layout.updateMenuPosition({reveal:false,markReady:false})
layout.updateMenuPosition()
layout.menuReady=true
layout.updateMenuPosition()
assert.deepEqual(readiness,[false,false,true])
console.log('PASS: Select forwards the hidden anchor phase; pre-ready option layout cannot skip the enter animation.')
