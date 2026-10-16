import { MediaClientFailure } from '../lifecycle/owner.js'

const barriers = new WeakMap()

/** All deferred media in a form finishes before one native submission resumes. */
export function registerMediaSubmit(form, { owner, pending, persist, onError = () => {} }) {
    owner.assertActive()
    if (!form?.addEventListener || typeof form.requestSubmit !== 'function'
        || typeof pending !== 'function' || typeof persist !== 'function') {
        throw new MediaClientFailure('invalid_configuration', 'Deferred media needs a native form and upload participant.')
    }
    let barrier = barriers.get(form)
    if (!barrier) {
        barrier = { participants: new Set(), busy: false, resumed: false, disposed: false }
        const notify = (error) => {
            for (const item of barrier.participants) {
                if (!item.owner.disposed) { try { item.onError(error) } catch {} }
            }
        }
        const listener = (event) => {
            if (barrier.resumed) { barrier.resumed = false; return }
            if (event.defaultPrevented || barrier.disposed) return
            let active
            try {
                active = [...barrier.participants].filter((item) => !item.owner.disposed && item.pending())
            } catch (cause) {
                event.preventDefault()
                event.stopImmediatePropagation()
                notify(new MediaClientFailure('submit_failed', 'Unable to inspect pending media.', cause))
                return
            }
            if (!active.length && !barrier.busy) return
            event.preventDefault()
            event.stopImmediatePropagation()
            if (barrier.busy) return
            barrier.busy = true
            const submitter = event.submitter
            const priorBusy = form.getAttribute('aria-busy')
            form.setAttribute('aria-busy', 'true')
            void (async () => {
                try {
                    const results = await Promise.allSettled(active.map((item) => Promise.resolve().then(() => item.persist())))
                    let failed = false
                    results.forEach((result, index) => {
                        if (result.status !== 'rejected') return
                        failed = true
                        try { active[index].onError(result.reason) } catch { /* One field cannot prevent other error presentation. */ }
                    })
                    if (failed || barrier.disposed) return
                    if ([...barrier.participants].some((item) => !item.owner.disposed && item.pending())) {
                        const error = new MediaClientFailure('submit_pending', 'Media changed while submitting. Submit again to upload the new capture.')
                        for (const item of barrier.participants) {
                            if (!item.owner.disposed && item.pending()) { try { item.onError(error) } catch {} }
                        }
                        return
                    }
                    barrier.resumed = true
                    try {
                        form.requestSubmit(submitter?.form === form && submitter.isConnected ? submitter : undefined)
                    } finally {
                        // Native HTML validity can block submit before dispatching an event.
                        barrier.resumed = false
                    }
                } catch (cause) {
                    notify(new MediaClientFailure('submit_failed', 'Unable to resume the native form submission.', cause))
                } finally {
                    barrier.busy = false
                    if (priorBusy === null) form.removeAttribute('aria-busy')
                    else form.setAttribute('aria-busy', priorBusy)
                }
            })()
        }
        barrier.listener = listener
        form.addEventListener('submit', listener, true)
        barriers.set(form, barrier)
    }
    const participant = { owner, pending, persist, onError }
    barrier.participants.add(participant)
    return owner.defer(() => {
        barrier.participants.delete(participant)
        if (barrier.participants.size) return
        barrier.disposed = true
        form.removeEventListener('submit', barrier.listener, true)
        barriers.delete(form)
    })
}
