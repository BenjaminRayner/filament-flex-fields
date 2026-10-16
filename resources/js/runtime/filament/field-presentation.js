import { componentAssetScope } from '../assets/component-scope.js'

const SEGMENT = '[data-fff-segment-overflow]'
const TIMEZONE = '[data-fff-timezone-boot]'

/** Native field presentation only. No factory, value, or asset ownership policy. */
export function createFilamentFieldPresentation({
    loadSegment = () => import('./presentation/segment.js'),
    loadTimezone = () => import('./presentation/timezone.js'),
} = {}) {
    let disposed = false
    let segment = null
    const pending = new Map()
    const load = (kind, loader) => {
        if (!pending.has(kind)) {
            const request = Promise.resolve().then(loader).then(module => {
                if (!disposed) {
                    if (kind === 'segment') segment = module
                }
                return module
            }).catch(error => { pending.delete(kind); throw error })
            pending.set(kind, request)
        }
        return pending.get(kind)
    }
    return {
        async prepare(root, { isActive = () => true, signal, instanceOnly = false } = {}) {
            if (disposed || signal?.aborted || root?.isConnected === false) return
            const targets = selector => {
                const elements = [...(root?.querySelectorAll?.(selector) ?? [])]
                if (root?.matches?.(selector)) elements.unshift(root)
                return elements
            }
            const eligible = element => !disposed && !signal?.aborted && element.isConnected !== false
                // Timezone's inert metadata marker is intentionally hidden. Its
                // containing field owns activation, not the metadata element.
                && isActive(element.matches(TIMEZONE) ? element.parentElement : element)
                && (!instanceOnly || componentAssetScope(element) === root)
            const segments = targets(SEGMENT).filter(element => eligible(element)
                && element.dataset?.ssrScrollPositioned !== 'true')
            const zones = targets(TIMEZONE).filter(element => eligible(element)
                && element.getAttribute('data-fff-timezone-boot-done') !== '1')
            await Promise.all([
                segments.length ? load('segment', loadSegment).then(module => {
                    for (const element of segments) if (eligible(element)) module.prepare(element)
                }) : null,
                zones.length ? load('timezone', loadTimezone).then(module => {
                    for (const element of zones) if (eligible(element)) module.prepare(element)
                }) : null,
            ])
        },
        preserveMorph(element, incoming) {
            if (disposed) return
            // Blocking first paint may precede the lazy ESM bridge. Its API is
            // the same compiled owner; never import in a synchronous morph hook.
            if (segment) segment.preserveMorph(element, incoming)
            else {
                const firstPaint = globalThis[Symbol.for('fff.first-paint.segment.v1')]
                firstPaint?.preservePositionedSegmentOverflow(element, incoming)
                firstPaint?.preserveSegmentPresentation(element, incoming)
            }
        },
        dispose() { disposed = true; segment = null; pending.clear() },
    }
}
