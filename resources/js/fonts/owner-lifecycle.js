import {isAssetElementActive} from '../runtime/lifecycle/asset-activation.js'
import {changesFilamentActivation} from '../runtime/filament/activation-mutation.js'

const documents = new WeakMap()

/** Observe owner ancestry only, never the font row mutations generated during scrolling. */
export function bindFontOwner(root, update) {
    const document = root.ownerDocument
    let group = documents.get(document)
    if (!group) {
        const callbacks = new Map(), window = document.defaultView
        let frame = null
        const pending = new Set()
        const enqueue = root => {
            pending.add(root)
            if (frame !== null) return
            frame = window.requestAnimationFrame(()=>{
                frame=null;const roots=[...pending];pending.clear()
                for (const root of roots) callbacks.get(root)?.(isAssetElementActive(root))
            })
        }
        const observer = new window.MutationObserver(mutations=>{
            for (const mutation of mutations) {
                if (mutation.type === 'attributes' && !changesFilamentActivation(mutation)) continue
                const nodes = mutation.type === 'attributes' ? [mutation.target] : [...mutation.removedNodes,...mutation.addedNodes]
                for (const root of callbacks.keys()) if (nodes.some(node=>node === root || node.contains?.(root))) enqueue(root)
            }
        })
        const observe = () => {
            observer.disconnect()
            if (document.body) observer.observe(document.body,{subtree:true,childList:true,attributes:true,attributeOldValue:true,
                attributeFilter:['class','style','hidden','aria-hidden','open','data-fff-asset-active']})
            for (const root of callbacks.keys()) enqueue(root)
        }
        const updateAll = () => {for (const root of callbacks.keys()) enqueue(root)}
        const events = ['x-modal-opened','modal-closed','x-modal-closed']
        document.addEventListener('livewire:navigated',observe)
        for (const event of events) document.addEventListener(event,updateAll)
        observe()
        group = {callbacks,dispose:()=>{
            observer.disconnect();if (frame !== null) window.cancelAnimationFrame(frame)
            pending.clear();document.removeEventListener('livewire:navigated',observe)
            for (const event of events) document.removeEventListener(event,updateAll)
            documents.delete(document)
        }}
        documents.set(document,group)
    }
    group.callbacks.set(root,update)
    update(isAssetElementActive(root))
    let released = false
    return () => {if (released) return;released=true;group.callbacks.delete(root);if (!group.callbacks.size) group.dispose()}
}
