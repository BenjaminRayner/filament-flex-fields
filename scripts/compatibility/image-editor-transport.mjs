import assert from 'node:assert/strict'
import { fileURLToPath } from 'node:url'
import { build } from 'esbuild'

const bundle = await build({ entryPoints: [fileURLToPath(new URL('../../resources/js/file-upload/editor/transport.js', import.meta.url))], bundle: true, write: false, format: 'esm', platform: 'node' })
const { default: createTransport } = await import(`data:text/javascript;base64,${Buffer.from(bundle.outputFiles[0].contents).toString('base64')}`)
const nativeComponentSrc = `data:text/javascript;base64,${Buffer.from(`export default config => ({state:{rawInterceptor:true},shouldUpdateState:true,async init(){this.pond={server:{process:(field,file,metadata,load,error,progress,abort)=>{this.finishNative=load;this.failNative=error;this.abortNative=abort;this.shouldUpdateState=false;return{abort(){abort()}}}},setOptions(options){if(options.server)this.server=options.server}};this.removeDraft=config.removeUploadedFileUsing;this.deleteDraft=config.deleteUploadedFileUsing},destroy(){this.destroyed=true}})`).toString('base64')}`
let resolveCapture, resolveCompletion, rejectCompletion
let reconciled = false, errors = 0
const captures = [], removals = [], completions = []
const state = { original: 'original.png' }
const vm = createTransport({ state, nativeComponentSrc,
    captureImageEditorFileUsing: key => { captures.push(key); return new Promise(resolve => { resolveCapture = resolve }) },
    completeImageEditorReplacementUsing: (...args) => { completions.push(args); return new Promise((resolve, reject) => { resolveCompletion = resolve; rejectCompletion = reject }) },
    reconcileImageEditorReplacementUsing: async () => reconciled,
    removeUploadedFileUsing: key => { removals.push(key) }, deleteUploadedFileUsing: key => { removals.push(key) },
})
vm.$nextTick = async () => {}
vm.$dispatch = () => {}
await vm.init()
assert.equal(vm.state, state, 'Lazy publication must retain initialized entangled state identity')
vm.editorReplacing = true; vm.editorReplacedKey = 'original'
let removed = false
const removal = vm.removeDraft('original').then(() => { removed = true })
await Promise.resolve(); await Promise.resolve()
assert.deepEqual(captures, ['original'])
assert.equal(removed, false, 'Capture must wait for server response')
await vm.removeDraft('another')
assert.deepEqual(removals, ['another'], 'Another file removal must not be treated as editor replacement')
resolveCapture({ fileKey: 'original', editorial: { alt: 'A photo' } })
await removal; await vm.waitForEditorRemoval()
assert.deepEqual(vm.editorReplacementMetadata.editorial, { alt: 'A photo' })
vm.editorReplacing = false
let loaded = null
vm.pond.server.process('field', {}, { fffEditorReplacement: vm.editorReplacementMetadata }, key => { loaded = key }, () => {}, () => {}, () => {})
vm.shouldUpdateState = true // A parallel native upload completes.
assert.equal(vm.shouldUpdateState, false, 'An unrelated upload cannot release the replacement transaction')
const completion = vm.finishNative('new')
await Promise.resolve()
assert.deepEqual(completions, [['new', 'original', { alt: 'A photo' }]])
assert.equal(loaded, null, 'Pond cannot mark complete before server association succeeds')
resolveCompletion(); await completion
assert.equal(loaded, 'new')
assert.equal(vm.shouldUpdateState, true)
assert.equal(vm.lastState, JSON.stringify(state))
vm.pond.server.process('field', {}, { fffEditorReplacement: { fileKey: 'original', editorial: null } }, () => {}, () => {}, () => {}, () => {})
vm.shouldUpdateState = true
vm.failNative('failed')
assert.equal(vm.shouldUpdateState, true, 'A failed upload must release the transaction guard')
vm.pond.server.process('field', {}, { fffEditorReplacement: { fileKey: 'original' } }, () => {}, () => { errors++ }, () => {}, () => {})
const rejectedCompletion = vm.finishNative('rejected')
rejectCompletion(new Error('Rejected metadata')); await rejectedCompletion
assert.equal(errors, 1, 'Unaccepted replacement must report failure after server reconciliation')
reconciled = true; loaded = null
vm.pond.server.process('field', {}, { fffEditorReplacement: { fileKey: 'original' } }, key => { loaded = key }, () => { errors++ }, () => {}, () => {})
const lostResponse = vm.finishNative('accepted')
rejectCompletion(new Error('Lost response')); await lostResponse
assert.equal(loaded, 'accepted', 'A lost response must retain the server-proven accepted replacement')
assert.equal(errors, 1)
vm.state.original = 'original.png'
const pendingEdit = {serverId:null,getMetadata:() => ({fileKey:'original',isStored:true})}
await vm.removePendingEditorOriginal(pendingEdit)
assert.deepEqual(removals, ['another', 'original'], 'Removing a pending manual edit must remove its retained original association through native authorization')
await vm.removePendingEditorOriginal({...pendingEdit,serverId:'new'})
assert.equal(removals.length, 2, 'An accepted replacement must use its own native removal path')
vm.destroy(); vm.destroy()
assert.equal(vm.destroyed, true)
console.log('Image editor transport: entanglement identity, scoped capture, server barrier, concurrent completion guard, native error release, lost-response reconciliation, pending manual removal and disposal passed.')
