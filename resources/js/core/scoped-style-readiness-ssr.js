/** One implementation shared by blocking CSS enhancement and runtime retainers. */
import * as implementation from '../runtime/assets/scoped-style-readiness.js'

const key = Symbol.for('fff.first-paint.readiness.v1')
const previous = globalThis[key]
if (previous && previous.version !== __FFF_FIRST_PAINT_VERSION__) {
    throw new Error('FLEX first-paint readiness incompatible; reload required.')
}
if (!previous) {
    Object.defineProperty(globalThis, key, { value: Object.freeze({
        ...implementation,
        version: __FFF_FIRST_PAINT_VERSION__,
        paint(script) {
            const document = script?.ownerDocument
            if (document?.defaultView) {
                implementation.primeScopedStyleReadiness({ document, window: document.defaultView })
            }
        },
    }) })
}
globalThis[key].paint(document.currentScript)
