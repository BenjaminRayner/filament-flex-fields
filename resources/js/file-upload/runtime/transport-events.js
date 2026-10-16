/** Bounded opt-in telemetry. Network failure never blocks native transport or changes file state. */
export function createUploadTransportEvents({ owner, config, send, source = () => 'file' }) {
    const enabled = new Set(config?.events ?? [])
    if (!enabled.size) return { observe() {}, record() {} }
    const session = globalThis.crypto.randomUUID()
    let sequence = 0
    let timer = null
    let sending = false
    const pending = []
    const lastProgress = new Map()
    const interval = Math.max(500, Math.min(10000, config.interval ?? 1000))
    const schedule = () => { if (!owner.disposed && !timer) timer = setTimeout(flush, interval) }
    async function flush() {
        timer = null
        if (owner.disposed || sending || !pending.length) { if (pending.length) schedule(); return }
        sending = true
        const batch = pending.splice(0, 32)
        try { await send(session, batch) } catch { /* Client observations are best effort, not a persistence outbox. */ }
        finally { sending = false; if (pending.length && !owner.disposed) schedule() }
    }
    function record(type, file = null, progress = null) {
        if (owner.disposed || !enabled.has(type) || (file?.origin === 3 && type !== 'removed')) return
        const fileId = file?.id ?? null
        if (type === 'progress') {
            if (!Number.isFinite(progress)) return
            const now = performance.now()
            if (now - (lastProgress.get(fileId) ?? -Infinity) < interval) return
            lastProgress.set(fileId, now)
        }
        if (pending.length >= 128) {
            const progressAt = pending.findIndex(event => event.type === 'progress')
            if (progressAt !== -1) pending.splice(progressAt, 1)
            else pending.shift()
        }
        const origin = file?.getMetadata?.('fffSource') ?? (file ? 'file' : source())
        pending.push({ type, sequence: ++sequence, fileId, progress: progress == null ? null : Math.max(0, Math.min(100, progress)), source: origin })
        if (['removed', 'completed', 'failed', 'cancelled'].includes(type)) lastProgress.delete(fileId)
        schedule()
    }
    function observe(snapshot) {
        const [first, second] = snapshot.args ?? []
        switch (snapshot.event) {
            case 'addfile': if (!first) record('added', second); else record('failed', second); break
            case 'processfilestart': record('started', first); break
            case 'processfileprogress': record('progress', first, second * 100); break
            case 'processfile': record(first ? 'failed' : 'completed', second); break
            case 'processfileabort': if (!snapshot.pausedIds?.has(first?.id)) record('cancelled', first); break
            case 'removefile': if (!first) record('removed', second); break
        }
    }
    owner.defer(() => { clearTimeout(timer); pending.length = 0; lastProgress.clear() })
    return { observe, record }
}
