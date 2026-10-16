import { MediaClientFailure } from '../lifecycle/owner.js'

/** Bounded same-origin staging fetch, cancelled with its consumer. */
export async function readPreviewFile(payload, { owner, maxBytes = 25 * 1024 * 1024, fetcher = fetch } = {}) {
    owner.assertActive()
    if (!Number.isSafeInteger(maxBytes) || maxBytes < 1 || typeof payload?.name !== 'string' || !payload.name || typeof payload?.previewUrl !== 'string' || !payload.previewUrl) {
        throw new MediaClientFailure('invalid_configuration', 'Imported file needs a name and byte budget.')
    }
    const address = new URL(payload.previewUrl, globalThis.location?.href)
    if (!['http:', 'https:'].includes(address.protocol) || address.origin !== globalThis.location?.origin) {
        throw new MediaClientFailure('preview_rejected', 'Staging preview must use the application origin.')
    }
    const response = await fetcher(address.href, { credentials: 'same-origin', cache: 'no-store', signal: owner.signal })
    if (!response.ok || !response.body?.getReader) throw new MediaClientFailure('preview_unavailable', 'Imported file preview is unavailable.')
    const declared = Number(response.headers.get('content-length'))
    const reader = response.body.getReader()
    const chunks = []
    let bytes = 0
    try {
        if (Number.isFinite(declared) && declared > maxBytes) throw new MediaClientFailure('size_rejected', 'Imported file exceeds its byte budget.')
        while (true) {
            owner.assertActive()
            const chunk = await reader.read()
            if (chunk.done) break
            bytes += chunk.value.byteLength
            if (bytes > maxBytes) throw new MediaClientFailure('size_rejected', 'Imported file exceeds its byte budget.')
            chunks.push(chunk.value)
        }
        owner.assertActive()
        if (!bytes) throw new MediaClientFailure('preview_unavailable', 'Imported file is empty.')
        return new File(chunks, payload.name, { type: typeof payload.type === 'string' ? payload.type : response.headers.get('content-type') ?? '' })
    } finally {
        try { await reader.cancel() } catch { /* Preserve the read/budget failure. */ }
        reader.releaseLock()
        chunks.length = 0
    }
}
