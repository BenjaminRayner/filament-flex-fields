/** Construction must finish synchronously before any owner starts acquisition. */
export function requireSynchronousAdapter(value, phase) {
    if (typeof value?.then === 'function') {
        // Reject this contract immediately, but consume an async author's late
        // rejection after construction rollback. It cannot install a runtime.
        Promise.resolve(value).catch(() => {})
        throw new TypeError(`FLEX runtime adapter ${phase} must be synchronous.`)
    }
    if (!value || typeof value !== 'object' || Array.isArray(value)) {
        throw new TypeError(`Invalid FLEX runtime adapter ${phase} contract.`)
    }
    return value
}
