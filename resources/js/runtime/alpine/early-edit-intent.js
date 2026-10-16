import { isAssetElementActive } from '../lifecycle/asset-activation.js'
import { isMountedFactoryReady } from '../../core/alpine-readiness.js'

/** Keep bounded edit intent until the explicitly opted-in owner is initialized. */
export function bindEarlyEditIntent(document) {
    const pending = new Map()
    const capture = event => {
        const node = event.target?.closest?.('[data-fff-edit-intent]')
        const owner = node?.closest?.('[x-fff-load]')
        if (!owner || isMountedFactoryReady(owner) || node.getAttribute('contenteditable') === 'false'
            || !isAssetElementActive(owner, { document })) return
        if (event.type === 'keydown' && (event.key === 'Tab' || ((event.ctrlKey || event.metaKey) && ['c', 'v'].includes(event.key.toLowerCase())))) return
        event.preventDefault(); event.stopImmediatePropagation()
        const events = pending.get(owner) || []
        pending.set(owner, events)
        if (events.length >= 128) return
        if (event.type === 'keydown') events.push({ node, type: event.type, values: { key: event.key, code: event.code,
            altKey: event.altKey, ctrlKey: event.ctrlKey, metaKey: event.metaKey, shiftKey: event.shiftKey } })
        else if (event.type === 'beforeinput') events.push({ node, type: event.type, values: { data: event.data?.slice(0, 256) || null, inputType: event.inputType } })
        else {
            const text = event.clipboardData?.getData('text/plain') || ''
            if (text.length <= 256) events.push({ node, type: 'paste', text })
        }
    }
    const clear = event => pending.delete(event.target)
    const cancel = () => pending.clear()
    const ready = event => {
        const owner = event.target, entries = pending.get(owner)
        if (!entries) return
        pending.delete(owner)
        queueMicrotask(() => {
            if (!owner.isConnected || !isMountedFactoryReady(owner) || !isAssetElementActive(owner, { document })) return
            const view = document.defaultView
            for (const { node, type, values, text } of entries) {
                if (!node.isConnected || !owner.contains(node)) continue
                const replay = type === 'keydown' ? new view.KeyboardEvent(type, { ...values, bubbles: true, cancelable: true })
                    : type === 'beforeinput' ? new view.InputEvent(type, { ...values, bubbles: true, cancelable: true })
                        : new view.Event(type, { bubbles: true, cancelable: true })
                if (type === 'paste') Object.defineProperty(replay, 'clipboardData', { value: { getData: () => text } })
                node.dispatchEvent(replay)
            }
        })
    }
    for (const type of ['keydown', 'beforeinput', 'paste']) document.addEventListener(type, capture, true)
    document.addEventListener('fff-load-ready', ready)
    document.addEventListener('fff-load-error', clear)
    document.addEventListener('fff-load-dispose', clear)
    document.addEventListener('livewire:navigating', cancel)
    const dispose = () => {
        cancel()
        for (const type of ['keydown', 'beforeinput', 'paste']) document.removeEventListener(type, capture, true)
        document.removeEventListener('fff-load-ready', ready)
        document.removeEventListener('fff-load-error', clear)
        document.removeEventListener('fff-load-dispose', clear)
        document.removeEventListener('livewire:navigating', cancel)
    }
    dispose.adopt = records => {
        for (const { node, type, values, text } of records) if (node.isConnected) capture({ target: node, type, ...values,
            clipboardData: { getData: () => text || '' }, preventDefault() {}, stopImmediatePropagation() {} })
    }
    return dispose
}
