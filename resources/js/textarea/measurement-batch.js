// Share layout phases, never field state or activation. A retiring owner removes
// only its job; the document's frame survives while other owners still need it.
const batches = new WeakMap()

export function enqueueTextareaMeasurement(document, task) {
    let batch = batches.get(document)
    if (!batch) {
        batch = { jobs: new Set(), frame: null }
        batches.set(document, batch)
    }
    let resolve, reject
    const promise = new Promise((done, fail) => { resolve = done; reject = fail })
    const job = { task, active: true, value: null, resolve, reject }
    batch.jobs.add(job)
    const settle = (entry, result = false) => {
        if (!entry.active) return
        entry.active = false
        entry.resolve(result)
    }
    if (batch.frame === null) {
        batch.frame = globalThis.requestAnimationFrame(() => {
            batch.frame = null
            const jobs = [...batch.jobs]
            batch.jobs.clear()
            for (const phase of ['read', 'prepare', 'height', 'commit']) {
                for (const entry of jobs) {
                    if (!entry.active) continue
                    try {
                        if (phase === 'read') {
                            entry.value = entry.task.read()
                            if (!entry.value) settle(entry)
                        } else entry.task[phase](entry.value)
                    } catch (error) {
                        entry.active = false
                        entry.reject(error)
                    }
                }
            }
            for (const entry of jobs) settle(entry, true)
        })
    }
    return {
        promise,
        cancel() {
            batch.jobs.delete(job)
            settle(job)
            if (!batch.jobs.size && batch.frame !== null) {
                globalThis.cancelAnimationFrame(batch.frame)
                batch.frame = null
            }
        },
    }
}
