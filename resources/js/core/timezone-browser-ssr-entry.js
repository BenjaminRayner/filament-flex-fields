/** Sole compiled owner of browser-timezone first paint and subsequent morph boot. */
import * as implementation from './timezone-browser-ssr-boot.js'

const key = Symbol.for('fff.first-paint.timezone.v1')
const previous = globalThis[key]
if (previous && previous.version !== __FFF_FIRST_PAINT_VERSION__) {
    throw new Error('FLEX first-paint timezone incompatible; reload required.')
}
if (!previous) {
    Object.defineProperty(globalThis, key, { value: Object.freeze({
        ...implementation,
        version: __FFF_FIRST_PAINT_VERSION__,
        paint(script) {
            const local = script?.previousElementSibling?.matches?.('[data-fff-timezone-boot]')
                ? script.previousElementSibling
                : script?.closest?.('.fff-timezone-field')?.querySelector?.('[data-fff-timezone-boot]')
            if (local) implementation.bootTimezoneBrowserSsrElement(local)
            else implementation.bootTimezoneBrowserSsrDefaults(document)
        },
    }) })
}
globalThis[key].paint(document.currentScript)
