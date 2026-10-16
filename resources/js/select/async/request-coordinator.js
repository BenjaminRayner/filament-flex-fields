/**
 * Logical cancellation works even when the underlying Livewire call cannot abort.
 * Tokens are object identities: cleanup of an old flight cannot end a newer one.
 */
export function createRequestCoordinator() {
    let disposed = false
    let epoch = 0
    const channels = new Map()
    const cancel = () => {
        epoch++
        for (const flight of channels.values()) flight.controller?.abort()
        channels.clear()
    }
    return {
        get disposed() { return disposed },
        get epoch() { return epoch },
        invalidate() { if (!disposed) cancel(); return epoch },
        begin(channel, { exclusive = false, context = null } = {}) {
            if (disposed || (exclusive && channels.has(channel))) return null
            channels.get(channel)?.controller?.abort()
            const flight = { epoch, context, controller: typeof AbortController === 'function' ? new AbortController() : null }
            channels.set(channel, flight)
            return {
                context,
                signal: flight.controller?.signal,
                current: () => !disposed && epoch === flight.epoch && channels.get(channel) === flight,
                finish() { if (channels.get(channel) === flight) channels.delete(channel) },
            }
        },
        dispose() { if (disposed) return; disposed = true; cancel() },
    }
}
