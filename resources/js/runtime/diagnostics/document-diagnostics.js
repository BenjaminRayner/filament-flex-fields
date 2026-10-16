import { getRuntimeState } from '../runtime-state.js'
import { createRuntimeDiagnostics } from './runtime-diagnostics.js'
import { assetPlanIdentity } from '../owners/plan-identity.js'
import { componentAssetScope, directAssetBatches } from '../assets/component-scope.js'

export function retainRuntimeDiagnostics({ document, window }) {
    const state = getRuntimeState()
    state.diagnostics ??= new WeakMap()
    let record = state.diagnostics.get(document)
    if (!record) {
        const collector = createRuntimeDiagnostics({ performance: window?.performance,
            emit: entry => {
                const Event = window?.CustomEvent ?? globalThis.CustomEvent
                if (Event && window?.dispatchEvent) window.dispatchEvent(new Event('fff:asset-error', { detail: entry }))
            } })
        record = { collector, owners: 0, planMarkers: new WeakMap(), planSequence: 0 }
        state.diagnostics.set(document, record)
    }
    record.owners++
    const subscriptions = new Set()
    let released = false
    return {
        report(error, context) { if (!released) return record.collector.report(error, context) },
        mark(name, context) { if (!released) record.collector.mark(name, context) },
        snapshot: record.collector.snapshot,
        identifyOwner: record.collector.identifyOwner,
        identifyPlan: record.collector.identifyPlan,
        subscribe(callback) {
            if (released) throw Error('FLEX diagnostic owner is disposed.')
            const unsubscribe = record.collector.subscribe(callback)
            const release = () => { subscriptions.delete(release); unsubscribe() }
            subscriptions.add(release)
            return release
        },
        release() {
            if (released) return
            released = true
            for (const unsubscribe of [...subscriptions]) unsubscribe()
            if (--record.owners) return
            if (state.diagnostics.get(document) === record) state.diagnostics.delete(document)
            record.collector.dispose()
            record.planMarkers = new WeakMap()
        },
    }
}

export function reportRuntimeError(document, error, context = {}) {
    return getRuntimeState().diagnostics?.get(document)?.collector.report(error, context)
}

function planIdentity(element) {
    if (!element?.dataset?.fffAssetConsumer) return null
    if (element.dataset.fffPlanId) return element.dataset.fffPlanId
    const record = getRuntimeState().diagnostics?.get(element.ownerDocument ?? globalThis.document)
    if (!record) return null
    const values = assetPlanIdentity(element), previous = record.planMarkers.get(element)
    if (previous && values.every((value, index) => value === previous.values[index])) return previous.id
    const id = `inline-${++record.planSequence}`
    record.planMarkers.set(element, { values, id })
    return id
}

export function diagnosticContext(element, context = {}) {
    if (!element?.dataset?.fffAssetConsumer) {
        const owner = componentAssetScope(element, context.componentId)
        element = directAssetBatches(owner, context.componentId)[0] ?? element
    }
    return { ...context,
        ownerId: element?.dataset?.fffAssetConsumerId ?? element?.id ?? null,
        planId: planIdentity(element),
        componentId: context.componentId ?? element?.dataset?.fffAssetConsumer ?? null }
}
