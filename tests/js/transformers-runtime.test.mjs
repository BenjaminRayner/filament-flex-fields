import assert from 'node:assert/strict'
import test from 'node:test'
import { loadTransformersRuntime, resetTransformersRuntimeForTests } from '../../resources/js/core/transformers-runtime.js'
import { transcribeAudioUrl, resetWhisperPipelineCacheForTests } from '../../resources/js/core/whisper-transcription.js'

function fixture(t, id) {
    const original = Object.fromEntries(['window', 'location', 'fetch', 'AudioContext', 'crossOriginIsolated'].map(key => [key, Object.getOwnPropertyDescriptor(globalThis, key)]))
    globalThis.window = globalThis
    globalThis.location = { href: 'https://example.test/admin/audio' }
    globalThis.crossOriginIsolated = false
    resetTransformersRuntimeForTests()
    resetWhisperPipelineCacheForTests()
    t.after(() => {
        resetTransformersRuntimeForTests()
        resetWhisperPipelineCacheForTests()
        for (const [key, descriptor] of Object.entries(original)) {
            if (descriptor) Object.defineProperty(globalThis, key, descriptor)
            else delete globalThis[key]
        }
    })
    const source = `export const env = { backends: { onnx: { wasm: {} } } }; export const calls = []; export async function pipeline(task, model, options) { calls.push({ task, model, options }); return async (audio, generation) => { calls.push({ audio: [...audio], generation }); return {text: '  Recognized speech.  '}; }; } // ${id}`
    return `data:text/javascript,${encodeURIComponent(source)}`
}

test('runtime loads once and binds both ONNX files to the installed version without isolation headers', async t => {
    const url = fixture(t, 'runtime')
    const first = loadTransformersRuntime(url, '/whisper/4.3.1/')
    assert.equal(loadTransformersRuntime(url, '/whisper/4.3.1/'), first)
    const { env } = await first
    assert.deepEqual(env.backends.onnx.wasm.wasmPaths, {
        mjs: 'https://example.test/whisper/4.3.1/ort-wasm-simd-threaded.mjs',
        wasm: 'https://example.test/whisper/4.3.1/ort-wasm-simd-threaded.wasm',
    })
    assert.equal(env.backends.onnx.wasm.numThreads, 1)
    assert.equal(env.backends.onnx.wasm.proxy, false)
    assert.equal(env.allowLocalModels, false)
    assert.equal(env.useBrowserCache, true)
})

for (const quantized of [true, false]) test(`transcription passes ${quantized ? 'q8' : 'fp32'} to the WASM pipeline and decodes at 16 kHz`, async t => {
    const url = fixture(t, String(quantized))
    const module = await import(url)
    const contexts = []
    let closed = 0
    globalThis.fetch = async () => new Response(new Uint8Array([1, 2]))
    globalThis.AudioContext = class {
        constructor(options) { contexts.push(options) }
        decodeAudioData() { return Promise.resolve({ numberOfChannels: 1, getChannelData: () => Float32Array.from([0.1, -0.1]) }) }
        async close() { closed++; this.state = 'closed' }
    }
    const result = await transcribeAudioUrl('/audio.wav', {
        model: 'Xenova/whisper-tiny.en', quantized, multilingual: false,
        task: 'transcribe', language: null, models: [], runtimeModuleUrl: url,
        runtimeWasmBaseUrl: '/whisper/4.3.1/',
    })
    assert.equal(result, 'Recognized speech.')
    assert.equal(module.calls[0].task, 'automatic-speech-recognition')
    assert.equal(module.calls[0].options.dtype, quantized ? 'q8' : 'fp32')
    assert.equal(module.calls[0].options.device, 'wasm')
    assert.equal('quantized' in module.calls[0].options, false)
    assert.deepEqual(contexts, [{ sampleRate: 16000 }])
    assert.equal(closed, 1)
    assert.equal(module.calls[1].generation.chunk_length_s, 30)
})

test('missing runtime configuration and canceled consumers do not load a model', async t => {
    fixture(t, 'cancelled')
    await assert.rejects(loadTransformersRuntime('', ''), /transcription_runtime_unconfigured/)
    const controller = new AbortController(); controller.abort()
    await assert.rejects(transcribeAudioUrl('/audio.wav', { signal: controller.signal }), { name: 'AbortError' })
})
