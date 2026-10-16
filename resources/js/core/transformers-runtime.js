/** @type {Promise<{ env: import('@huggingface/transformers').env, pipeline: import('@huggingface/transformers').pipeline }> | null} */
let runtimePromise = null

/**
 * @param {string} moduleUrl
 * @param {string} wasmBaseUrl
 */
export function loadTransformersRuntime(moduleUrl, wasmBaseUrl) {
    if (! moduleUrl || ! wasmBaseUrl) {
        return Promise.reject(new Error('transcription_runtime_unconfigured'))
    }

    if (! runtimePromise) {
        runtimePromise = import(/* webpackIgnore: true */ moduleUrl).then(({ env, pipeline }) => {
            env.allowLocalModels = false
            env.allowRemoteModels = true
            env.useBrowserCache = true

            if (env.backends?.onnx?.wasm) {
                env.backends.onnx.wasm.proxy = false
                env.backends.onnx.wasm.wasmPaths = {
                    mjs: new URL('ort-wasm-simd-threaded.mjs', new URL(wasmBaseUrl, globalThis.location?.href)).href,
                    wasm: new URL('ort-wasm-simd-threaded.wasm', new URL(wasmBaseUrl, globalThis.location?.href)).href,
                }
                // Normal Filament pages do not require cross-origin isolation.
                env.backends.onnx.wasm.numThreads = globalThis.crossOriginIsolated ? Math.min(4, globalThis.navigator?.hardwareConcurrency || 1) : 1
            }

            return { env, pipeline }
        }).catch((error) => {
            runtimePromise = null

            throw error
        })
    }

    return runtimePromise
}

export function resetTransformersRuntimeForTests() {
    runtimePromise = null
}
