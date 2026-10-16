const sameSnapshot = (left, right) => left.length === right.length && left.every((value, index) => Object.is(value, right[index]))

/** Interest in an operation belongs to one owner generation, not its transport.
 * Releasing a lease never cancels imports/links retained by other consumers.
 * Weak keys do not retain detached DOM. Snapshots are local and never exported.
 */
export function createGenerationLeases({ snapshot, available = owner => owner.isConnected !== false, invalidate = () => {} }) {
    const owners = new WeakMap()
    const acquire = (owner, { replace = false } = {}) => {
        const previous = owners.get(owner)
        if (!replace && previous?.current()) return previous
        previous?.release()
        // Abort/cleanup can synchronously start a newer attempt. Adopt a valid
        // successor; never overwrite it with an un-aborted, unreachable lease.
        const successor = owners.get(owner)
        if (successor) {
            if (successor.current()) return successor
            throw new Error('FLEX owner changed during reentrant generation replacement. Retry the current operation.')
        }
        const identity = snapshot(owner)
        const controller = new AbortController()
        const lease = {
            signal: controller.signal,
            owns: () => owners.get(owner) === lease && !controller.signal.aborted,
            current() {
                if (!lease.owns()) return false
                if (available(owner) && sameSnapshot(identity, snapshot(owner))) return true
                lease.release()
                return false
            },
            release() {
                if (!lease.owns()) return
                // Reentrant abort/cleanup may acquire the next generation.
                owners.delete(owner)
                try { invalidate(owner) } finally { controller.abort() }
            },
        }
        owners.set(owner, lease)
        return lease
    }
    return { acquire, release: owner => owners.get(owner)?.release() }
}
