/** Batch geometry across Todo instances: every read precedes every DOM write.
 * Jobs have an instance owner and are cancelled when that instance is destroyed.
 */
const documents = new WeakMap()

export function queueTodoLayout(
    root,
    read,
    onError = (error) => {
        queueMicrotask(() => {
            throw error
        })
    },
) {
    const document = root.ownerDocument
    let batch = documents.get(document)
    if (!batch) {
        batch = { jobs: new Map(), frame: null }
        documents.set(document, batch)
    }
    const job = { read, onError }
    batch.jobs.set(root, job)
    if (batch.frame === null) {
        batch.frame = document.defaultView.requestAnimationFrame(() => {
            batch.frame = null
            const jobs = [...batch.jobs]
            batch.jobs.clear()
            const writes = []
            for (const [owner, job] of jobs) {
                if (owner.isConnected === false) continue
                try {
                    writes.push([owner, job.read(), job.onError])
                } catch (error) {
                    job.onError(error)
                }
            }
            for (const [owner, write, report] of writes) {
                if (owner.isConnected === false) continue
                try {
                    write?.()
                } catch (error) {
                    report(error)
                }
            }
        })
    }
    return () => {
        // Cancelling a superseded job must not cancel its replacement.
        if (batch.jobs.get(root) === job) batch.jobs.delete(root)
        if (!batch.jobs.size && batch.frame !== null) {
            document.defaultView.cancelAnimationFrame(batch.frame)
            batch.frame = null
        }
    }
}

const fontReadiness = new WeakMap()

/** FontFaceSet.ready may flush layout. Read it once before any Todo instance writes DOM. */
export function todoFontsReady(root) {
    const document = root.ownerDocument
    if (!fontReadiness.has(document))
        fontReadiness.set(document, document.fonts?.ready ?? Promise.resolve())
    return fontReadiness.get(document)
}
