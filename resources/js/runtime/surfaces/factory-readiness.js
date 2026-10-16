import { changesAssetActivation } from '../lifecycle/activation-mutation.js'
import { createOwnerTreeIndex } from '../owners/owner-tree-index.js'
import { isDomAssetElementActive } from '../lifecycle/dom-activation.js'
import { isMountedFactoryReady } from '../../core/alpine-readiness.js'

/** Retry only failed active instances; mounted neighbors keep their factories. */
export function retrySurfaceFactories(root, { document, window, isActive = element => isDomAssetElementActive(element, { document }) }) {
    const fields = [...(root.matches?.('[x-fff-load]') ? [root] : []), ...root.querySelectorAll?.('[x-fff-load]') ?? []]
    for (const field of fields) {
        if (field._x_async === 'failed' && isActive(field)) {
            field.dispatchEvent(new window.CustomEvent('fff-load-retry'))
        }
    }
}

/** Runs after asset preparation, never inside awaitBundleReady (no circular gate). */
export function awaitSurfaceFactories(root, { document, window, signal, isActive = element => isDomAssetElementActive(element, { document }), navigationEvent = null, closeEvent = null, timeoutMs = 15_000, excludeLocalPaint = false, initialFields = null, changesActivation = changesAssetActivation }) {
    if (signal?.aborted || !root.isConnected || !isActive(root)) return Promise.resolve()
    const owners = createOwnerTreeIndex(), fields = new Set(), waiting = new Set(), failed = new Set()
    const discover = node => [...(node.matches?.('[x-fff-load]') ? [node] : []), ...node.querySelectorAll?.('[x-fff-load]') ?? []]
    const update = field => {
        waiting.delete(field); failed.delete(field)
        if (field.isConnected === false || (root !== field && root.contains && !root.contains(field)) || field.hasAttribute?.('x-fff-load') === false) {
            fields.delete(field); owners.remove(field); return
        }
        fields.add(field); owners.bind(field, field)
        if ((excludeLocalPaint && field.closest?.('[data-fff-local-paint]')) || !isActive(field)) return
        if (field._x_async === 'failed') failed.add(field)
        else if (!isMountedFactoryReady(field)) waiting.add(field)
    }
    for (const field of initialFields ?? discover(root)) update(field)
    if (!waiting.size && !failed.size) return Promise.resolve()
    return new Promise((resolve, reject) => {
        let timer, finished = false
        const observer = new window.MutationObserver(mutations => {
            const affected = new Set()
            for (const mutation of mutations) {
                if (mutation.type === 'attributes' && ['class', 'style'].includes(mutation.attributeName) && !changesActivation(mutation)) continue
                if (mutation.type === 'attributes' && ['x-fff-load', 'data-fff-alpine-ready'].includes(mutation.attributeName)) {
                    if (fields.has(mutation.target) || mutation.target.matches?.('[x-fff-load]')) affected.add(mutation.target)
                    continue
                }
                const nodes = mutation.type === 'attributes' ? [mutation.target] : [...mutation.addedNodes, ...mutation.removedNodes]
                for (const node of nodes) {
                    for (const field of owners.within(node)) affected.add(field)
                    if (node.isConnected !== false) for (const field of discover(node)) affected.add(field)
                }
            }
            for (const field of affected) update(field)
            check()
        })
        const finish = error => {
            if (finished) return
            finished = true
            observer.disconnect(); window.clearTimeout(timer)
            root.removeEventListener('fff-load-ready', changed)
            root.removeEventListener('fff-load-error', changed)
            if (closeEvent) window.removeEventListener(closeEvent, closed)
            if (navigationEvent) document.removeEventListener?.(navigationEvent, navigated)
            signal?.removeEventListener('abort', abort)
            fields.clear(); waiting.clear(); failed.clear(); owners.clear()
            if (error) reject(error)
            else resolve()
        }
        const abort = () => finish()
        const check = () => {
            if (signal?.aborted || !root.isConnected || !isActive(root)) finish()
            else if (failed.size) finish(new Error('Flex Fields component initialization failed.'))
            else if (!waiting.size) finish()
        }
        const changed = event => {
            const affected = event.target?.matches?.('[x-fff-load]') ? [event.target] : owners.within(event.target)
            for (const field of new Set(affected)) update(field)
            check()
        }
        const closed = event => {
            const modal = document.getElementById?.(event?.detail?.id)
            if (modal) for (const field of owners.within(modal)) update(field)
            check()
        }
        const navigated = () => { for (const field of [...fields]) update(field); check() }
        observer.observe(root, { subtree: true, childList: true, attributes: true, attributeOldValue: true, attributeFilter: ['x-fff-load', 'data-fff-local-paint', 'data-fff-alpine-ready', 'class', 'style', 'hidden', 'aria-hidden', 'data-fff-asset-active', 'open'] })
        root.addEventListener('fff-load-ready', changed); root.addEventListener('fff-load-error', changed)
        if (closeEvent) window.addEventListener(closeEvent, closed)
        if (navigationEvent) document.addEventListener?.(navigationEvent, navigated)
        signal?.addEventListener('abort', abort, { once: true })
        timer = window.setTimeout(() => finish(new Error('Flex Fields component readiness timed out.')), timeoutMs)
        check()
    })
}
