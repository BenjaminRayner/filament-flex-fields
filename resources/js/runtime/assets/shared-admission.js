import { getRuntimeState } from '../runtime-state.js'
import { createAssetLoadScheduler } from './asset-load-scheduler.js'

/** Shared by explicit link loads and native module evaluation in one document. */
export function retainAssetAdmission(document) {
    if (!document || (typeof document !== 'object' && typeof document !== 'function')) {
        throw Error('FLEX asset admission requires a document owner.')
    }
    const state = getRuntimeState()
    state.assetAdmission ??= new WeakMap()
    let record = state.assetAdmission.get(document)
    if (!record) {
        record = { scheduler: createAssetLoadScheduler(), owners: 0, flights: new Set() }
        state.assetAdmission.set(document, record)
    }
    record.owners++
    let released = false
    const retire = () => {
        if (record.owners || record.flights.size) return
        if (state.assetAdmission.get(document) === record) state.assetAdmission.delete(document)
        record.scheduler.dispose()
    }
    return {
        scheduler: record.scheduler,
        trackFlight(promise) {
            if (record.flights.has(promise)) return
            record.flights.add(promise)
            const finished = () => { record.flights.delete(promise); retire() }
            promise.then(finished, finished)
        },
        release() {
            if (released) return
            released = true
            record.owners--
            retire()
        },
    }
}

export function assetAdmissionSnapshot(document) {
    const record = getRuntimeState().assetAdmission?.get(document)
    if (!record) return null
    const scheduler = record.scheduler
    return { ...scheduler.snapshot(), owners: record.owners, trackedFlights: record.flights.size, imports: [...scheduler.inflight.keys()].filter(key => key.startsWith('evaluation:')).length }
}
