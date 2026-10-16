import { MediaClientFailure } from '../lifecycle/owner.js'

/** Native temporary transport only; permanent persistence remains a server form operation. */
export function createLivewireUpload({ owner, wire, path, maxBytes = Infinity, onProgress = () => {} }) {
    if (!owner || typeof wire?.upload !== 'function' || typeof path !== 'string' || !path
        || typeof maxBytes !== 'number' || maxBytes <= 0 || Number.isNaN(maxBytes)) {
        throw new MediaClientFailure('invalid_configuration', 'A media upload needs an owner, native transport and state path.')
    }
    let current = null
    let disposed = false

    function cancel() {
        const operation = current
        if (!operation) return
        operation.finish(new MediaClientFailure('upload_cancelled', 'Temporary media upload was cancelled.'))
        try { wire.cancelUpload?.(path) } catch { /* Native temporary cleanup remains TTL-based. */ }
    }

    function start(file) {
        owner.assertActive()
        if (disposed) throw new MediaClientFailure('owner_disposed', 'Media transport has been disposed.')
        if (!(file instanceof Blob) || file.size < 1 || file.size > maxBytes) {
            throw new MediaClientFailure('size_rejected', 'Captured media does not meet its byte limits.')
        }
        if (current) throw new MediaClientFailure('upload_busy', 'This state path already has an upload.')
        return new Promise((resolve, reject) => {
            const operation = {
                finish(error, result) {
                    if (current !== operation) return
                    current = null
                    error ? reject(error) : resolve(result)
                },
            }
            current = operation
            try {
                wire.upload(path, file,
                    (filename) => {
                        if (current !== operation || owner.disposed) return
                        if (typeof filename !== 'string' || !filename) {
                            operation.finish(new MediaClientFailure('invalid_response', 'Temporary upload returned no file identity.'))
                            return
                        }
                        operation.finish(null, filename)
                    },
                    (cause) => operation.finish(new MediaClientFailure('upload_failed', 'Temporary media upload failed.', cause)),
                    (event) => {
                        if (current !== operation || owner.disposed) return
                        const progress = Number(event?.detail?.progress)
                        if (Number.isFinite(progress)) {
                            try { onProgress(Math.min(100, Math.max(0, progress))) } catch { /* Presentation cannot invalidate native transport. */ }
                        }
                    },
                    () => operation.finish(new MediaClientFailure('upload_cancelled', 'Temporary media upload was cancelled.')),
                )
            } catch (cause) {
                operation.finish(new MediaClientFailure('upload_failed', 'Temporary media upload failed.', cause))
            }
        })
    }

    const dispose = () => { disposed = true; cancel() }
    owner.defer(dispose)
    return { start, cancel, dispose, get uploading() { return current !== null } }
}
