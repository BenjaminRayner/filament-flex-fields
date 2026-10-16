import { createCardRuntime } from '../../resources/js/choice-cards/runtime.js'
import { createMatrixRuntime } from '../../resources/js/matrix-choice/runtime.js'

export function fixture(matrix=false, multiple=true, images=false, initial=matrix ? {r:['a'],s:[]} : multiple ? ['a'] : 'a') {
    const old={window:globalThis.window,Alpine:globalThis.Alpine,requestAnimationFrame:globalThis.requestAnimationFrame,cancelAnimationFrame:globalThis.cancelAnimationFrame}
    const watches=new Set(),observers=new Set(),frames=new Map(),listeners=new Map(),errors=[]
    globalThis.window={FffAssetInjector:{isElementActive:() => true}}
    globalThis.Alpine={watch(getter,callback) {const item={getter,callback};watches.add(item);return () => watches.delete(item)}}
    let seq=0,rtl=false,focus=null
    globalThis.requestAnimationFrame=callback => {frames.set(++seq,callback);return seq}
    globalThis.cancelAnimationFrame=id => frames.delete(id)
    const classes=values => {const set=new Set(values);return {contains:key=>set.has(key),add:key=>set.add(key),toggle(key,enabled) {if (enabled) set.add(key);else set.delete(key)}}}
    const node=(key,row='r',col=0,checked=false) => {
        const attrs=new Map(),input={checked,defaultChecked:checked,disabled:false,tabIndex:0,focus(){focus=key}}
        const indicator={dataset:{checked:String(checked)}}
        const element={dataset:matrix ? {matrixRow:row==='r'?'0':'1',matrixCol:String(col),rowKey:row,columnKey:key} : {cardOption:key},
            input,classList:classes(checked?['is-selected']:[]),tabIndex:0,
            getAttribute:key=>attrs.get(key),setAttribute:(key,value)=>attrs.set(key,value),
            querySelector:selector=>selector==='input'?input:indicator,focus(){focus=`${row}:${key}`},
        }
        return element
    }
    let nodes=matrix ? [node('a','r',0,true),node('b','r',1),node('locked','r',2),node('a','s',0),node('b','s',1)] : [node('a','r',0,true),node('b'),node('locked'),node('c')]
    const imageListeners=new Map(),image={complete:true,naturalWidth:100,loading:'lazy',classList:classes([]),addEventListener(name,fn){imageListeners.set(name,fn)},removeEventListener(name){imageListeners.delete(name)}}
    const config=matrix ? {mode:multiple?'checkbox':'radio',rows:[{value:'r',max_selections:2},{value:'s'}],columns:[{value:'a'},{value:'b'},{value:'locked',disabled:true}],conditionalDisableRules:[{type:'cell',row:'s',column:'b',when_row:'r',when_columns:['b']}],disabledCells:{}} : {multiple,disabled:false,maxSelections:2,options:[{value:'a'},{value:'b'},{value:'locked',disabled:true},{value:'c'}]}
    const root={isConnected:true,dataset:matrix?{matrixConfig:JSON.stringify(config)}:{cardConfig:JSON.stringify(config)},classList:classes([]),matches:()=>rtl,contains:el=>nodes.includes(el),
        querySelectorAll(selector){if(selector.includes('image'))return images?[image]:[];if(selector.includes('row-key'))return [];return nodes},
        addEventListener:(name,fn)=>listeners.set(name,fn),removeEventListener:(name,fn)=>{if(listeners.get(name)===fn)listeners.delete(name)},
        ownerDocument:{defaultView:{MutationObserver:class {constructor(fn){this.fn=fn;observers.add(this)} observe(){} disconnect(){observers.delete(this)}},IntersectionObserver:class{constructor(fn){this.fn=fn;observers.add(this)} observe(){} unobserve(){} disconnect(){observers.delete(this)}}}},
    }
    const runtime=matrix?createMatrixRuntime({state:initial}):createCardRuntime({state:initial},images)
    runtime.$el=root;runtime.$dispatch=(name,data)=>errors.push({name,data})
    return {root,runtime,nodes,errors,listeners,watches,observers,frames,image,imageListeners,
        get focus(){return focus},set rtl(value){rtl=value},
        event(type,key,extra={}) {const node=nodes.find(el=>matrix?el.dataset.columnKey===key:el.dataset.cardOption===key);const target=type==='change'?node.input:{closest:()=>node};if(type==='change'){target.closest=()=>node;target.checked=!target.checked}const event={target,preventDefault(){this.prevented=true},...extra};listeners.get(type)?.(event);return event},
        external(state){runtime.state=state;for(const watch of watches)watch.callback()},
        morph(change){change(config,nodes);root.dataset[matrix?'matrixConfig':'cardConfig']=JSON.stringify(config);for(const observer of observers)observer.fn([{type:'attributes',target:root}]);const work=[...frames.values()];frames.clear();for(const fn of work)fn()},
        restore(){runtime.destroy();Object.assign(globalThis,old)},
    }
}
