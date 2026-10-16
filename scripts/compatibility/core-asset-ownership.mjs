import assert from 'node:assert/strict'
import {pathToFileURL} from 'node:url'
import path from 'node:path'
const root = path.resolve(import.meta.dirname, '../..')
const {createConsumerGraph} = await import(pathToFileURL(`${root}/resources/js/runtime/assets/consumer-graph.js`))
const {createAssetLoadScheduler} = await import(pathToFileURL(`${root}/resources/js/runtime/assets/asset-load-scheduler.js`))
const errors = []
let graph
let acquireCalls = 0
graph = createConsumerGraph({registry: {a: {stylesheets:['shared.css']}}, onAcquire(){acquireCalls++}, onError(e, phase){errors.push(phase)}, onRelease(id){graph.release(id); throw Error('cleanup fault')}})
graph.acquire('one','a'); graph.acquire('two','a')
graph.release('one'); graph.release('one')
assert.equal(graph.getRefCount('shared.css'),1)
assert.deepEqual(graph.getActiveConsumerIds(),['two'])
let retire = 0
graph.onRefZero('shared.css', () => {throw Error('observer fault')})
graph.onRefZero('shared.css', () => {retire++})
graph.release('two')
await new Promise(r=>setTimeout(r,190))
assert.equal(retire,1); assert.equal(graph.getRefCount('shared.css'),0)
assert.deepEqual(errors,['release','release','retirement'])
graph.reset()
graph.onRefZero('shared.css', () => graph.acquire('new','a'))
graph.onRefZero('shared.css', () => {retire++})
graph.acquire('old','a');graph.release('old')
await new Promise(r=>setTimeout(r,190))
assert.equal(retire,1); assert.equal(graph.getRefCount('shared.css'),1)
graph.reset()
const asynchronousErrors = []
const asynchronous = createConsumerGraph({
    registry: {a: {stylesheets:['async.css']}},
    onAcquire: async () => {throw Error('asynchronous callback fault')},
    onError: (error, phase) => asynchronousErrors.push(phase),
})
asynchronous.acquire('async','a')
await new Promise(r=>setImmediate(r))
assert.deepEqual(asynchronousErrors,['acquire'])
assert.equal(asynchronous.getRefCount('async.css'),1)
asynchronous.reset()
let loads = 0
const scheduler = createAssetLoadScheduler()
const pending = scheduler.schedule('late',()=>{loads++})
scheduler.dispose()
await assert.rejects(pending,/disposed/)
assert.equal(loads,0)
assert.equal(scheduler.snapshot().active,0)
assert.equal(scheduler.inflight.size,0)
console.log('PASS: throwing/reentrant cleanup, retirement isolation/reacquisition and disposal before the run microtask.')
