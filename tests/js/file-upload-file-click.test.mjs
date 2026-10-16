import {test} from 'node:test'
import assert from 'node:assert/strict'
import {activateUploadedFile,canAddUploadFiles} from '../../resources/js/file-upload/runtime/file-click.js'

function harness() {
    const file={pondId:'native',complete:true},calls=[],navigation=[]
    const owner={disposed:false,assertActive(){if(this.disposed)throw Error('retired')}}
    const vm={files:[file],fileClickPending:{},sourceError:error=>error.message,$wire:{async callSchemaComponentMethod(...args){calls.push(args);return 'https://app.example/documents/1'}}}
    const pond={async whenReady(){return {pond:{getFiles:()=>[{id:'native',origin:1,serverId:'owned-key'}]},component:{}}}}
    const config={hasUploadedFileClick:true,schemaComponentKey:'field'},requests=new Set()
    return {file,calls,navigation,owner,vm,pond,config,requests,run(){return activateUploadedFile({...this,navigate:url=>navigation.push(url)})}}
}

test('resolves native keys, sends only the canonical key and retires pending UI after a response',async()=>{
    const h=harness();await h.run()
    assert.deepEqual(h.calls,[['field','activateUploadedFile',{fileKey:'owned-key'}]])
    assert.deepEqual(h.navigation,['https://app.example/documents/1'])
    assert.deepEqual(h.vm.fileClickPending,{})
})
test('deduplicates a file click while keeping another file independently actionable',async()=>{
    const h=harness(),finishes=[]
    const other={pondId:'other',complete:true};h.vm.files.push(other)
    h.pond.whenReady=async()=>({pond:{getFiles:()=>[{id:'native',origin:1,serverId:'owned-key'},{id:'other',origin:1,serverId:'other-key'}]},component:{}})
    h.vm.$wire.callSchemaComponentMethod=(...args)=>{h.calls.push(args);return new Promise(resolve=>finishes.push(resolve))}
    const first=h.run();await new Promise(setImmediate);await h.run()
    const second=activateUploadedFile({...h,file:other});await new Promise(setImmediate)
    assert.equal(h.requests.size,2);assert.deepEqual(h.vm.fileClickPending,{native:true,other:true})
    assert.equal(h.calls.length,2)
    finishes[0](null);await first;assert.equal(h.vm.fileClickPending.native,undefined);assert.equal(h.vm.fileClickPending.other,true)
    finishes[1](null);await second;assert.equal(h.requests.size,0)
})
test('late responses cannot navigate or update a retired owner',async()=>{
    const h=harness();let finish
    h.vm.$wire.callSchemaComponentMethod=()=>new Promise(resolve=>{finish=resolve})
    const pending=h.run();await new Promise(setImmediate);h.owner.disposed=true
    finish('https://app.example/documents/1');await pending;assert.deepEqual(h.navigation,[])
})
test('ignores incomplete, failed, removed and disabled items',async()=>{
    for(const patch of [{complete:false},{failed:true},{leaving:true}]){const h=harness();Object.assign(h.file,patch);await h.run();assert.equal(h.calls.length,0)}
    const h=harness();h.config.isDisabled=true;await h.run();assert.equal(h.calls.length,0)
})
test('rejects a stale native key and invalid returned URLs',async()=>{
    for(const url of ['javascript:alert(1)','data:text/html,x','https://user:pass@app.example/',{},'https://app.example/\n']){
        const h=harness();h.vm.$wire.callSchemaComponentMethod=async()=>url;await h.run()
        assert.deepEqual(h.navigation,[]);assert.ok(h.vm.actionError)
    }
    const h=harness();h.vm.files=[];await h.run();assert.equal(h.calls.length,0)
})
test('capacity reserves queued, failed and transferring files; removal restores adding and singles retain replacement',()=>{
    const config={isMultiple:true,maxFiles:5},files=Array.from({length:5},(_,i)=>({failed:i===0,uploading:i===1}))
    assert.equal(canAddUploadFiles(config,files),false)
    files[0].leaving=true;assert.equal(canAddUploadFiles(config,files),true)
    assert.equal(canAddUploadFiles({...config,isDisabled:true},[]),false)
    assert.equal(canAddUploadFiles({...config,isMultiple:false},files),true)
    assert.equal(canAddUploadFiles({isMultiple:true},files),true)
})


test('normalizes numeric restored-file indices into string keys at the RPC boundary',async()=>{
    const h=harness()
    h.pond.whenReady=async()=>({pond:{getFiles:()=>[{id:'native',origin:3,source:'documents/a.txt'}]},component:{uploadedFileIndex:{'documents/a.txt':0}}})
    await h.run();assert.deepEqual(h.calls,[['field','activateUploadedFile',{fileKey:'0'}]])
})
