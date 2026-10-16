import { isAssetElementActive } from '../lifecycle/asset-activation.js'
import { isMountedFactoryReady } from '../../core/alpine-readiness.js'

/** Retain an explicit SSR picker click while its owner waits for CSS/factory init. */
export function bindEarlyPickerOpenIntent(document) {
    const pending = new Map()
    const clear = owner => {
        const entry = pending.get(owner)
        entry?.trigger.removeAttribute('aria-busy')
        if (entry) owner.removeEventListener('fff-load-dispose', entry.dispose)
        pending.delete(owner)
    }
    const click = event => {
        const trigger = event.target?.closest?.('[data-fff-open-trigger]')
        const owner = trigger?.closest?.('[x-fff-load]')
        // Module evaluation is not factory readiness: init() may still await native editors.
        if (!owner || trigger.disabled || isMountedFactoryReady(owner)) return
        if (!isAssetElementActive(owner, { document })) return
        event.preventDefault()
        // An uninitialized child must not dispatch into an ancestor Alpine scope.
        event.stopImmediatePropagation?.()
        const selecting = trigger.dataset?.fffIntent === 'select'
        if (pending.has(owner)) { clear(owner); if (!selecting) return }
        trigger.setAttribute('aria-busy', 'true')
        const dispose = () => clear(owner)
        owner.addEventListener('fff-load-dispose', dispose)
        pending.set(owner, { trigger, dispose })
    }
    const ready = event => {
        const owner = event.target
        const trigger = pending.get(owner)?.trigger
        if (!trigger) return
        clear(owner)
        // Synchronous factory init can finish before Alpine binds child handlers.
        queueMicrotask(() => {
            if (owner.isConnected && trigger.isConnected && !trigger.disabled && isMountedFactoryReady(owner)
                && isAssetElementActive(owner, { document })) trigger.click()
        })
    }
    const outside = event => {
        for (const owner of pending.keys()) if (!owner.contains(event.target)) clear(owner)
    }
    const cancel = () => { for (const owner of pending.keys()) clear(owner) }
    const error = event => clear(event.target)
    const keydown = event => { if (event.key === 'Escape') cancel() }
    document.addEventListener('click', click, true)
    document.addEventListener('pointerdown', outside, true)
    document.addEventListener('fff-load-ready', ready)
    document.addEventListener('fff-load-error', error)
    document.addEventListener('fff-load-dispose', error)
    document.addEventListener('keydown', keydown, true)
    document.addEventListener('livewire:navigating', cancel)
    const dispose = () => {
        cancel()
        document.removeEventListener('click', click, true)
        document.removeEventListener('pointerdown', outside, true)
        document.removeEventListener('fff-load-ready', ready)
        document.removeEventListener('fff-load-error', error)
        document.removeEventListener('fff-load-dispose', error)
        document.removeEventListener('keydown', keydown, true)
        document.removeEventListener('livewire:navigating', cancel)
    }
    dispose.adopt = records => {
        for (const { node } of records) if (node.isConnected) click({ target: node, preventDefault() {}, stopImmediatePropagation() {} })
    }
    return dispose
}
