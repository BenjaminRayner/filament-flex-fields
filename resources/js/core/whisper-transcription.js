import { loadTransformersRuntime } from './transformers-runtime.js'
import { loadAudioBuffer } from '../audio/load-buffer.js'

const STEREO_MIX_SCALING_FACTOR = Math.sqrt(2)
const MODEL_LOAD_TIMEOUT_MS = 600_000

/** @type {Map<string, Promise<import('@huggingface/transformers').AutomaticSpeechRecognitionPipeline>>} */
const pipelineCache = new Map()

/**
 * @param {string} model
 * @param {boolean} multilingual
 * @param {Array<{ id: string }>} models
 */
export function resolveWhisperModelId(model, multilingual, models) {
    if (model.startsWith('distil-whisper/')) {
        return model
    }

    if (! multilingual) {
        if (model.endsWith('.en')) {
            return model
        }

        const englishVariant = `${model}.en`

        if (models.some((entry) => entry.id === englishVariant)) {
            return englishVariant
        }

        return model
    }

    if (model.endsWith('.en')) {
        return model.replace(/\.en$/, '')
    }

    return model
}

/**
 * @param {AudioBuffer} audioBuffer
 */
export function audioBufferToWhisperSamples(audioBuffer) {
    if (audioBuffer.numberOfChannels === 1) {
        return audioBuffer.getChannelData(0)
    }

    const left = audioBuffer.getChannelData(0)
    const right = audioBuffer.getChannelData(1)
    const mono = new Float32Array(left.length)

    for (let index = 0; index < left.length; index++) {
        mono[index] = (STEREO_MIX_SCALING_FACTOR * ((left[index] ?? 0) + (right[index] ?? 0))) / 2
    }

    return mono
}

/**
 * @param {string} url
 */
export async function fetchAudioBuffer(url, options = {}) {
    // Whisper expects 16 kHz samples regardless of the playback device rate.
    return loadAudioBuffer(url, { ...options, sampleRate: 16000 })
}

function active(signal) {
    if (signal?.aborted) throw new DOMException('Transcription cancelled', 'AbortError')
}
async function awaitConsumer(promise, signal) {
    active(signal)
    if (!signal) return promise
    let abort
    try {
        return await Promise.race([promise, new Promise((_, reject) => {
            abort = () => reject(new DOMException('Transcription cancelled', 'AbortError'))
            signal.addEventListener('abort', abort, { once: true })
        })])
    } finally { signal.removeEventListener('abort', abort) }
}

/**
 * @param {Promise<T>} promise
 * @param {number} timeoutMs
 * @param {string} errorCode
 * @returns {Promise<T>}
 * @template T
 */
async function withTimeout(promise, timeoutMs, errorCode) {
    let timeoutId

    try {
        return await Promise.race([
            promise,
            new Promise((_, reject) => {
                timeoutId = window.setTimeout(() => {
                    reject(new Error(errorCode))
                }, timeoutMs)
            }),
        ])
    } finally {
        window.clearTimeout(timeoutId)
    }
}

/**
 * @param {(phase: string, detail?: { file?: string, progress?: number }) => void} [onProgress]
 */
function createModelProgressHandler(onProgress) {
    return (data) => {
        if (! data || typeof data !== 'object') {
            return
        }

        if (data.status === 'progress' && typeof data.progress === 'number') {
            onProgress?.('loading_model', {
                file: typeof data.file === 'string' ? data.file : '',
                progress: Math.round(data.progress),
            })

            return
        }

        if (data.status === 'initiate' && typeof data.file === 'string') {
            onProgress?.('loading_model', {
                file: data.file,
                progress: 0,
            })
        }
    }
}

/**
 * @param {string} modelId
 * @param {boolean} quantized
 * @param {string} runtimeModuleUrl
 * @param {string} runtimeWasmBaseUrl
 * @param {(phase: string, detail?: { file?: string, progress?: number }) => void} [onProgress]
 */
async function loadWhisperPipeline(modelId, quantized, runtimeModuleUrl, runtimeWasmBaseUrl, onProgress) {
    const cacheKey = `${runtimeModuleUrl}:${runtimeWasmBaseUrl}:${modelId}:${quantized ? 'q' : 'f'}`

    if (pipelineCache.has(cacheKey)) {
        return pipelineCache.get(cacheKey)
    }

    const promise = withTimeout(
        loadTransformersRuntime(runtimeModuleUrl, runtimeWasmBaseUrl).then(({ pipeline }) => (
            pipeline('automatic-speech-recognition', modelId, {
                dtype: quantized ? 'q8' : 'fp32',
                device: 'wasm',
                progress_callback: createModelProgressHandler(onProgress),
                revision: modelId.includes('/whisper-medium') ? 'no_attentions' : 'main',
            })
        )),
        MODEL_LOAD_TIMEOUT_MS,
        'transcription_model_timeout',
    ).catch((error) => {
        pipelineCache.delete(cacheKey)

        throw error
    })

    pipelineCache.set(cacheKey, promise)

    return promise
}

/**
 * @param {string} url
 * @param {{
 *   model: string,
 *   quantized: boolean,
 *   multilingual: boolean,
 *   language: string|null,
 *   task: string,
 *   models: Array<{ id: string }>,
 *   runtimeModuleUrl: string,
 *   runtimeWasmBaseUrl: string,
 * }} options
 * @param {(phase: string, detail?: { file?: string, progress?: number }) => void} [onProgress]
 */
export async function transcribeAudioUrl(url, options, onProgress) {
    active(options.signal)
    onProgress?.('loading_model')

    const modelId = resolveWhisperModelId(options.model, options.multilingual, options.models)
    const transcriber = await awaitConsumer(loadWhisperPipeline(
        modelId,
        options.quantized,
        options.runtimeModuleUrl,
        options.runtimeWasmBaseUrl,
        onProgress,
    ), options.signal)
    active(options.signal)

    onProgress?.('loading_audio')

    const audioBuffer = await fetchAudioBuffer(url, { signal: options.signal })
    active(options.signal)
    const audio = audioBufferToWhisperSamples(audioBuffer)

    onProgress?.('transcribing_phase')

    /** @type {Record<string, unknown>} */
    const generateOptions = {
        top_k: 0,
        do_sample: false,
        chunk_length_s: 30,
        stride_length_s: 5,
        return_timestamps: false,
        task: options.multilingual ? options.task : null,
        language: options.multilingual && options.language ? options.language : null,
    }

    const result = await awaitConsumer(transcriber(audio, generateOptions), options.signal)
    active(options.signal)

    if (typeof result === 'string') {
        return result.trim()
    }

    if (result && typeof result.text === 'string') {
        return result.text.trim()
    }

    return ''
}

export function modelSupportsMultilingual(modelId, models) {
    const entry = models.find((model) => model.id === modelId)

    if (entry && typeof entry.multilingual === 'boolean') {
        return entry.multilingual
    }

    return ! modelId.endsWith('.en')
}

export function resetWhisperPipelineCacheForTests() {
    pipelineCache.clear()
}
