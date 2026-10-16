const RESPONSE_BYTES = 1048576

export class GeocodingApiError extends Error {
    constructor(message, { status = null, payload = null } = {}) {
        super(message)
        this.name = 'GeocodingApiError'
        this.status = status
        this.payload = payload
    }
}

async function readResponse(response) {
    const length = Number(response.headers?.get?.('content-length'))
    if (length > RESPONSE_BYTES) throw new GeocodingApiError('Geocoding response exceeds its byte budget.')
    if (response.body?.getReader) {
        const reader = response.body.getReader()
        const chunks = []
        let bytes = 0
        try {
            for (;;) {
                const { done, value } = await reader.read()
                if (done) break
                bytes += value.byteLength
                if (bytes > RESPONSE_BYTES) {
                    await reader.cancel()
                    throw new GeocodingApiError('Geocoding response exceeds its byte budget.')
                }
                chunks.push(value)
            }
        } finally { reader.releaseLock() }
        const buffer = new Uint8Array(bytes)
        let offset = 0
        for (const chunk of chunks) { buffer.set(chunk, offset); offset += chunk.byteLength }
        return JSON.parse(new TextDecoder().decode(buffer))
    }
    const payload = await response.json()
    if (new TextEncoder().encode(JSON.stringify(payload)).length > RESPONSE_BYTES) throw new GeocodingApiError('Geocoding response exceeds its byte budget.')
    return payload
}

/** AbortSignal and bounded JSON transport; no document-global address/token cache. */
export async function requestGeocoding(url, { body = null, signal } = {}) {
    signal?.throwIfAborted?.()
    const response = await fetch(url, body === null ? { signal } : {
        method: 'POST', signal, credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json', Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
    })
    let payload
    try { payload = await readResponse(response) }
    catch (error) {
        if (signal?.aborted || error?.name === 'AbortError' || error instanceof GeocodingApiError) throw error
        throw new GeocodingApiError('Invalid geocoding response.', { status: response.status })
    }
    signal?.throwIfAborted?.()
    if (!response.ok || payload?.error) throw new GeocodingApiError('Geocoding request failed.', { status: response.status })
    if (!payload || typeof payload !== 'object' || Array.isArray(payload)) throw new GeocodingApiError('Invalid geocoding response.')
    return payload
}
