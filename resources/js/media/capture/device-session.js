import { MediaClientFailure } from '../lifecycle/owner.js'

/** Explicit permission acquisition. A late native result never revives a closed owner. */
export function createDeviceSession({ owner, devices = globalThis.navigator?.mediaDevices } = {}) {
    if (!owner || typeof devices?.getUserMedia !== 'function') {
        throw new MediaClientFailure('capture_unavailable', 'Media capture is unavailable in this browser or context.')
    }
    let generation = 0
    let pending = false
    let stream = null
    let releaseStream = () => {}
    let cancelPermission = () => {}
    let closed = false

    function close() {
        generation++
        pending = false
        cancelPermission()
        cancelPermission = () => {}
        releaseStream()
        releaseStream = () => {}
        stream = null
    }

    async function open(constraints) {
        owner.assertActive()
        if (closed) throw new MediaClientFailure('owner_disposed', 'Capture session has been disposed.')
        if (pending || stream) throw new MediaClientFailure('capture_busy', 'Close the active device session before opening another.')
        if (!constraints || (!constraints.audio && !constraints.video)) {
            throw new MediaClientFailure('invalid_configuration', 'Capture needs audio or video constraints.')
        }
        pending = true
        const attempt = ++generation
        let removeAbort = () => {}
        const cancelled = new Promise((_, reject) => {
            cancelPermission = () => reject(new MediaClientFailure('capture_cancelled', 'Device permission request was cancelled.'))
            removeAbort = owner.listen(owner.signal, 'abort', cancelPermission, { once: true })
        })
        const requested = Promise.resolve().then(() => {
            if (attempt !== generation || owner.disposed || closed) {
                throw new MediaClientFailure('capture_cancelled', 'Device permission request was cancelled.')
            }
            return devices.getUserMedia(constraints)
        }).then((result) => {
            if (attempt !== generation || owner.disposed || closed) {
                // Registering after disposal also stops tracks immediately.
                owner.trackStream(result)()
                throw new MediaClientFailure('capture_cancelled', 'Device permission completed after cancellation.')
            }
            releaseStream = owner.trackStream(result)
            stream = result
            return result
        }).catch((cause) => {
            if (cause instanceof MediaClientFailure) throw cause
            const reason = ({ NotAllowedError: 'permission_denied', NotFoundError: 'device_missing',
                NotReadableError: 'device_busy', OverconstrainedError: 'constraints_rejected', SecurityError: 'capture_unavailable' })[cause?.name]
                ?? 'capture_failed'
            throw new MediaClientFailure(reason, 'Unable to open the requested media device.', cause)
        })
        try {
            return await Promise.race([requested, cancelled])
        } finally {
            removeAbort()
            if (attempt === generation) { pending = false; cancelPermission = () => {} }
        }
    }

    const dispose = () => { closed = true; close() }
    owner.defer(dispose)
    return { open, close, dispose, get stream() { return stream }, get pending() { return pending } }
}
