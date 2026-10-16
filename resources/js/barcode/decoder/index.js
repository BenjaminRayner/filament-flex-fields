import { nativeCoversFormats, nativeFormat, mapEngineFormatToSlug, clampScanInterval } from './formats.js'
import { nativeMediaOperation } from '../../media/lifecycle/native-operation.js'
import { createResourceScope } from '../../runtime/lifecycle/resource-scope.js'

let fallbackModule
const loadFallback = () => fallbackModule ??= import('./zxing.js').catch(error => { fallbackModule = null; throw error })

export async function createBarcodeDecoder({ video, formats, owner, Detector = globalThis.BarcodeDetector, fallback = loadFallback }) {
    owner.assertActive()
    if (typeof Detector === 'function' && typeof Detector.getSupportedFormats === 'function') {
        try {
            const supported = await nativeMediaOperation(Detector.getSupportedFormats(), owner)
            if (nativeCoversFormats(supported, formats)) {
                const detector = new Detector({ formats: formats.map(nativeFormat) })
                return {
                    engine: 'native',
                    async decode() {
                        const codes = await nativeMediaOperation(detector.detect(video), owner)
                        for (const code of codes) {
                            const format = mapEngineFormatToSlug(code.format)
                            if (format && formats.includes(format)) return { value: code.rawValue, format }
                        }
                        return null
                    },
                }
            }
        } catch (error) { if (owner.disposed) throw error }
    }
    owner.assertActive()
    const module = await nativeMediaOperation(fallback(), owner)
    owner.assertActive()
    return module.createZxingDecoder({ video, formats, owner })
}

/** At most one decode in flight, no replay of a result after closing. */
export function startDecodeLoop({ decoder, video, owner, interval, found, failed, missing = () => {} }) {
    const scope = createResourceScope({ signal: owner.signal })
    const cadence = clampScanInterval(interval)
    const tick = async () => {
        if (scope.disposed) return
        const start = performance.now()
        try {
            if (video.readyState >= 2) {
                const result = await decoder.decode()
                if (!scope.disposed) {
                    if (result) found({ ...result, engine: decoder.engine })
                    else missing()
                }
            }
        } catch (error) {
            if (!scope.disposed) { scope.dispose(); failed(error) }
            return
        }
        if (!scope.disposed) scope.timeout(tick, Math.max(16, Math.round(cadence - (performance.now() - start))))
    }
    const stop = owner.defer(() => scope.dispose())
    void tick()
    return stop
}
