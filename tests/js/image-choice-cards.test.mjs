import test from 'node:test'
import assert from 'node:assert/strict'
import { fixture } from './card-matrix-fixture.mjs'

for (const images of [false,true]) {
    test(`${images?'image':'text'} cards: lazy initialization is idempotent; native input toggles exactly once`,async () => {
        const f=fixture(false,true,images)
        try {const ready=f.runtime.init();assert.equal(f.runtime.init(),ready);await ready;assert.equal(f.watches.size,2);f.event('change','b');assert.deepEqual(f.runtime.state,['a','b']);assert.equal(f.nodes[3].input.disabled,true);f.event('change','a');assert.deepEqual(f.runtime.state,['b']);assert.equal(f.nodes[3].input.disabled,false);assert.deepEqual(f.errors,[])}finally{f.restore()}
        assert.equal(f.watches.size,0);assert.equal(f.listeners.size,0);assert.equal(f.observers.size,0)
    })
    test(`${images?'image':'text'} cards: radio keyboard skips locks and honors RTL`,async () => {
        const f=fixture(false,false,images)
        try {await f.runtime.init();f.event('keydown','b',{key:'ArrowRight'});assert.equal(f.runtime.state,'c');f.rtl=true;f.event('keydown','b',{key:'ArrowRight'});assert.equal(f.runtime.state,'a');f.event('keydown','a',{key:' ',repeat:true});assert.equal(f.runtime.state,'a');assert.deepEqual(f.errors,[])}finally{f.restore()}
    })
    test(`${images?'image':'text'} cards: adopts a valid native pre-hydration click`,async () => {
        const f=fixture(false,false,images)
        try {f.nodes[0].input.checked=false;f.nodes[1].input.checked=true;await f.runtime.init();assert.equal(f.runtime.state,'b')}finally{f.restore()}
    })
    test(`${images?'image':'text'} cards: external state and changed server limits remain synchronized`,async () => {
        const f=fixture(false,true,images)
        try {await f.runtime.init();f.external(['b']);assert.equal(f.nodes[0].input.checked,false);assert.equal(f.nodes[1].input.checked,true);f.morph(config=>{config.maxSelections=1});assert.equal(f.nodes[0].input.disabled,true);assert.deepEqual(f.errors,[])}finally{f.restore()}
    })
}

test('image listeners, warming observer and scheduled morph are disposed with the field',async () => {
    const f=fixture(false,true,true)
    try {f.image.complete=false;await f.runtime.init();assert.equal(f.imageListeners.size,2);assert.equal(f.observers.size,2);for(const observer of f.observers)if(observer.fn) {if(observer.constructor.name==='MutationObserver')observer.fn([{type:'attributes'}])}f.runtime.destroy();assert.equal(f.imageListeners.size,0);assert.equal(f.observers.size,0);assert.equal(f.frames.size,0)}finally{f.restore()}
})

test('initially disabled image selections clear without clearing unrelated keys',async () => {
    const f=fixture(false,true,true,['a','locked'])
    try {await f.runtime.init();assert.deepEqual(f.runtime.state,['a']);assert.equal(f.nodes[2].input.disabled,true)}finally{f.restore()}
})

test('destruction before activation performs no late installation',async () => {
    const f=fixture()
    try {const ready=f.runtime.init();f.runtime.destroy();await ready;assert.equal(f.listeners.size,0);assert.equal(f.watches.size,0)}finally{f.restore()}
})


test('indicator springs only after a changed selection, never initial hydration or unchanged renders', async () => {
    const f=fixture()
    try {
        await f.runtime.init()
        assert.equal(f.nodes[0].dataset.cardMotion,undefined)
        f.runtime.render()
        assert.equal(f.nodes[0].dataset.cardMotion,undefined)
        f.event('change','b')
        assert.equal(f.nodes[1].dataset.cardMotion,'selected')
        f.event('change','b')
        assert.equal(f.nodes[1].dataset.cardMotion,'cleared')
    } finally { f.restore() }
})


test('single-selection cards mark the same spring motion on the newly selected and cleared cards', async () => {
    const f=fixture(false,false)
    try {
        await f.runtime.init()
        assert.equal(f.nodes[0].dataset.cardMotion,undefined)
        f.event('change','b')
        assert.equal(f.runtime.state,'b')
        assert.equal(f.nodes[0].dataset.cardMotion,'cleared')
        assert.equal(f.nodes[1].dataset.cardMotion,'selected')
        assert.equal(f.nodes[0].input.checked,false)
        assert.equal(f.nodes[1].input.checked,true)
    } finally { f.restore() }
})
