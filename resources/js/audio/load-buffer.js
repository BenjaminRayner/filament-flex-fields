import { createMediaOwner } from '../media/lifecycle/owner.js'
import { nativeMediaOperation } from '../media/lifecycle/native-operation.js'

/** Bounded fetch/decode used by waveform analysis and optional transcription. */
export async function loadAudioBuffer(url, { signal, maxBytes = 32 * 1024 * 1024, sampleRate } = {}) {
    const owner = createMediaOwner(), abort = () => owner.dispose()
    signal?.addEventListener('abort', abort, { once: true })
    if (signal?.aborted) owner.dispose()
    try {
        owner.assertActive()
        const response = await nativeMediaOperation(fetch(url, { signal: owner.signal }), owner, 20000)
        if (!response.ok) throw Error(`audio_fetch_failed:${response.status}`)
        if (Number(response.headers.get('content-length')) > maxBytes) throw Error('audio_size_rejected')
        let bytes
        if (response.body?.getReader) {
            const reader = response.body.getReader(), parts = []
            let count = 0
            try {
                while (true) {
                    const result = await nativeMediaOperation(reader.read(), owner, 20000)
                    if (result.done) break
                    count += result.value.byteLength
                    if (count > maxBytes) throw Error('audio_size_rejected')
                    parts.push(result.value)
                }
                bytes = new Uint8Array(count)
                let offset = 0
                for (const part of parts) { bytes.set(part, offset); offset += part.byteLength }
            } finally { await reader.cancel().catch(() => {}); reader.releaseLock() }
        } else {
            bytes = await nativeMediaOperation(response.arrayBuffer(), owner, 20000)
            if (bytes.byteLength > maxBytes) throw Error('audio_size_rejected')
        }
        owner.assertActive()
        const Context = globalThis.AudioContext || globalThis.webkitAudioContext
        if (!Context) throw Error('audio_context_unavailable')
        const context = new Context(sampleRate ? { sampleRate } : undefined)
        owner.defer(() => { if (context.state !== 'closed') void context.close().catch(() => {}) })
        return await nativeMediaOperation(context.decodeAudioData(bytes.buffer ?? bytes), owner, 8000)
    } finally { signal?.removeEventListener('abort', abort); owner.dispose() }
}
